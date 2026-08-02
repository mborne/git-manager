<?php

namespace App\Tests\Unit\Analysis\Checker;

use MBO\GitManager\Analysis\Checker\Trivy\TrivyRunner;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Process\ProcessResult;
use MBO\GitManager\Process\ProcessRunnerInterface;
use MBO\GitManager\Storage\GitRepositoryStore;
use MBO\GitManager\Storage\ReportStoreException;
use MBO\GitManager\Storage\ReportStoreInterface;
use MBO\GitManager\Storage\TempFilesystem;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

final class TrivyCheckerTest extends TestCase
{
    private const DATA_DIR = '/data';
    private const REPOSITORY_PATH = self::DATA_DIR.'/github.com/mborne/demo';
    private const PROJECT_ID = '0b7b2b2e-1e2a-3d4f-8a9b-0c1d2e3f4a5b';
    private const VERSION_OUTPUT = 'Version: 0.58.1';

    /**
     * A config path that is guaranteed to be missing so that the commands are
     * built without "--config".
     */
    private string $configPath;

    /**
     * The reports written to the store, indexed by "{toolName}/{projectId}".
     *
     * @var array<string,string>
     */
    private array $storedReports = [];

    protected function setUp(): void
    {
        $this->storedReports = [];
        $this->configPath = sys_get_temp_dir().'/git-manager-missing-'.uniqid().'.yaml';
    }

    private function createProject(): Project
    {
        $project = new Project();
        $project->setId(Uuid::fromString(self::PROJECT_ID));
        $project->setFullName('github.com/mborne/demo');

        return $project;
    }

    /**
     * Write a JSON report to the path requested by a "trivy fs" command.
     *
     * @param string[] $command
     */
    private function writeReport(array $command, string $jsonContent): void
    {
        $index = array_search('--output', $command, true);
        $this->assertIsInt($index);
        file_put_contents($command[$index + 1], $jsonContent);
    }

    /**
     * ReportStore recording the written reports.
     */
    private function createReportStore(bool $writable = true): ReportStoreInterface
    {
        $reportStore = $this->createStub(ReportStoreInterface::class);
        $reportStore
            ->method('write')
            ->willReturnCallback(
                function (string $toolName, Uuid $projectId, string $content) use ($writable): void {
                    if (!$writable) {
                        throw new ReportStoreException('fail to write the report');
                    }
                    $this->storedReports[$toolName.'/'.$projectId] = $content;
                }
            )
        ;

        return $reportStore;
    }

    private function createChecker(
        bool $trivyEnabled,
        ProcessRunnerInterface $processRunner,
        ?ReportStoreInterface $reportStore = null,
    ): TrivyChecker {
        return new TrivyChecker(
            $trivyEnabled,
            new TrivyRunner($processRunner, new TempFilesystem(), $this->configPath),
            new GitRepositoryStore(self::DATA_DIR, new NullLogger()),
            $reportStore ?? $this->createReportStore(),
            new NullLogger()
        );
    }

    /**
     * ProcessRunner answering according to the trivy subcommand and writing the
     * given JSON content to the report path.
     */
    private function createProcessRunner(
        ProcessResult $version,
        ProcessResult $scan,
        ?string $jsonContent = null,
    ): ProcessRunnerInterface {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(
                function (array $command) use ($version, $scan, $jsonContent): ProcessResult {
                    if ('--version' === $command[1]) {
                        return $version;
                    }
                    // the scan must target the git repository of the project
                    $this->assertContains(self::REPOSITORY_PATH, $command);
                    if (null !== $jsonContent) {
                        $this->writeReport($command, $jsonContent);
                    }

                    return $scan;
                }
            )
        ;

        return $processRunner;
    }

    /**
     * Build a trivy report content with the given vulnerabilities.
     *
     * @param array<string,string> $severityById
     */
    private function createTrivyContent(array $severityById): string
    {
        $vulnerabilities = [];
        foreach ($severityById as $id => $severity) {
            $vulnerabilities[] = [
                'VulnerabilityID' => $id,
                'Severity' => $severity,
            ];
        }
        $content = json_encode([
            'Results' => [
                [
                    'Target' => 'composer.lock',
                    'Vulnerabilities' => $vulnerabilities,
                ],
            ],
        ]);
        $this->assertIsString($content);

        return $content;
    }

    /**
     * ProcessRunner reporting an available trivy and a successful scan.
     */
    private function createSuccessfulProcessRunner(?string $jsonContent = null): ProcessRunnerInterface
    {
        return $this->createProcessRunner(
            new ProcessResult(0, self::VERSION_OUTPUT, ''),
            new ProcessResult(0, '', ''),
            $jsonContent
        );
    }

    public function testGetName(): void
    {
        $checker = $this->createChecker(true, $this->createStub(ProcessRunnerInterface::class));

        $this->assertSame('trivy', $checker->getName());
    }

    public function testDisabledByConfiguration(): void
    {
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->never())
            ->method('run')
        ;
        $checker = $this->createChecker(false, $processRunner);

        $this->assertNull($checker->check($this->createProject()));
    }

    public function testDisabledWhenTrivyIsMissing(): void
    {
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->once())
            ->method('run')
            ->with(['trivy', '--version'])
            ->willReturn(new ProcessResult(127, '', 'trivy: command not found'))
        ;
        $checker = $this->createChecker(true, $processRunner);

        $this->assertNull($checker->check($this->createProject()));
    }

    public function testScanFailure(): void
    {
        $processRunner = $this->createProcessRunner(
            new ProcessResult(0, self::VERSION_OUTPUT, ''),
            new ProcessResult(1, '', 'unexpected failure')
        );
        $checker = $this->createChecker(true, $processRunner);

        $this->assertSame([
            'success' => false,
            'vulnerabilities' => false,
            'summary' => false,
        ], $checker->check($this->createProject()));
    }

    public function testMissingReport(): void
    {
        $checker = $this->createChecker(true, $this->createSuccessfulProcessRunner());

        $this->assertSame([
            'success' => false,
            'vulnerabilities' => false,
            'summary' => false,
        ], $checker->check($this->createProject()));
    }

    public function testStoreFailure(): void
    {
        $checker = $this->createChecker(
            true,
            $this->createSuccessfulProcessRunner($this->createTrivyContent([])),
            $this->createReportStore(writable: false)
        );

        $this->assertSame([
            'success' => false,
            'vulnerabilities' => false,
            'summary' => false,
        ], $checker->check($this->createProject()));
    }

    public function testScanWithVulnerabilities(): void
    {
        $processRunner = $this->createSuccessfulProcessRunner($this->createTrivyContent([
            'CVE-2024-0001' => 'HIGH',
            'CVE-2024-0002' => 'CRITICAL',
            'CVE-2024-0003' => 'HIGH',
        ]));
        $checker = $this->createChecker(true, $processRunner);

        $this->assertSame([
            'success' => true,
            'vulnerabilities' => [
                'CVE-2024-0002' => 'CRITICAL',
                'CVE-2024-0001' => 'HIGH',
                'CVE-2024-0003' => 'HIGH',
            ],
            'summary' => [
                'HIGH' => 2,
                'CRITICAL' => 1,
            ],
        ], $checker->check($this->createProject()));
    }

    public function testScanWithoutVulnerability(): void
    {
        $checker = $this->createChecker(
            true,
            $this->createSuccessfulProcessRunner($this->createTrivyContent([]))
        );

        $this->assertSame([
            'success' => true,
            'vulnerabilities' => [],
            'summary' => [
                'HIGH' => 0,
                'CRITICAL' => 0,
            ],
        ], $checker->check($this->createProject()));
    }

    public function testReportIsStored(): void
    {
        $jsonContent = $this->createTrivyContent(['CVE-2024-0001' => 'HIGH']);
        $checker = $this->createChecker(true, $this->createSuccessfulProcessRunner($jsonContent));

        $checker->check($this->createProject());

        $this->assertSame(
            ['trivy/'.self::PROJECT_ID => $jsonContent],
            $this->storedReports
        );
    }

    public function testAvailabilityIsResolvedOnce(): void
    {
        $jsonContent = $this->createTrivyContent(['CVE-2024-0001' => 'HIGH']);
        $subCommands = [];
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(
                function (array $command) use (&$subCommands, $jsonContent): ProcessResult {
                    $subCommands[] = $command[1];
                    if ('--version' === $command[1]) {
                        return new ProcessResult(0, self::VERSION_OUTPUT, '');
                    }
                    $this->writeReport($command, $jsonContent);

                    return new ProcessResult(0, '', '');
                }
            )
        ;
        $checker = $this->createChecker(true, $processRunner);

        $project = $this->createProject();
        $checker->check($project);
        $checker->check($project);

        $this->assertSame(['--version', 'fs', 'fs'], $subCommands);
    }
}
