<?php

namespace App\Tests\Unit\Analysis\Checker;

use MBO\GitManager\Analysis\Checker\Gitleaks\GitleaksRunner;
use MBO\GitManager\Analysis\Checker\GitleaksChecker;
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

final class GitleaksCheckerTest extends TestCase
{
    private const DATA_DIR = '/data';
    private const REPOSITORY_PATH = self::DATA_DIR.'/github.com/mborne/demo';
    private const PROJECT_ID = '0b7b2b2e-1e2a-3d4f-8a9b-0c1d2e3f4a5b';

    /**
     * A default config path that is guaranteed to be missing so that the
     * commands are built without "--config".
     */
    private string $defaultConfigPath;

    /**
     * The reports written to the store, indexed by "{toolName}/{projectId}".
     *
     * @var array<string,string>
     */
    private array $storedReports = [];

    protected function setUp(): void
    {
        $this->storedReports = [];
        $this->defaultConfigPath = sys_get_temp_dir().'/git-manager-missing-'.uniqid().'.toml';
    }

    private function createProject(): Project
    {
        $project = new Project();
        $project->setId(Uuid::fromString(self::PROJECT_ID));
        $project->setFullName('github.com/mborne/demo');

        return $project;
    }

    /**
     * Write a SARIF report to the path requested by a "gitleaks detect" command.
     *
     * @param string[] $command
     */
    private function writeReport(array $command, string $sarifContent): void
    {
        $index = array_search('--report-path', $command, true);
        $this->assertIsInt($index);
        file_put_contents($command[$index + 1], $sarifContent);
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
        bool $gitleaksEnabled,
        ProcessRunnerInterface $processRunner,
        ?ReportStoreInterface $reportStore = null,
    ): GitleaksChecker {
        return new GitleaksChecker(
            $gitleaksEnabled,
            new GitleaksRunner($processRunner, new TempFilesystem(), $this->defaultConfigPath),
            new GitRepositoryStore(self::DATA_DIR, new NullLogger()),
            $reportStore ?? $this->createReportStore(),
            new NullLogger()
        );
    }

    /**
     * ProcessRunner answering according to the gitleaks subcommand and writing
     * the given SARIF content to the report path.
     */
    private function createProcessRunner(
        ProcessResult $version,
        ProcessResult $detect,
        ?string $sarifContent = null,
    ): ProcessRunnerInterface {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(
                function (array $command, ?string $workingDirectory = null) use ($version, $detect, $sarifContent): ProcessResult {
                    if ('version' === $command[1]) {
                        return $version;
                    }
                    // the scan must run in the git repository of the project
                    $this->assertSame(self::REPOSITORY_PATH, $workingDirectory);
                    if (null !== $sarifContent) {
                        $this->writeReport($command, $sarifContent);
                    }

                    return $detect;
                }
            )
        ;

        return $processRunner;
    }

    /**
     * Build a SARIF report content with the given ruleIds.
     *
     * @param string[] $ruleIds
     */
    private function createSarifContent(array $ruleIds): string
    {
        $results = [];
        foreach ($ruleIds as $ruleId) {
            $results[] = ['ruleId' => $ruleId];
        }
        $content = json_encode(['runs' => [['results' => $results]]]);
        $this->assertIsString($content);

        return $content;
    }

    /**
     * ProcessRunner reporting an available gitleaks and a successful scan.
     */
    private function createSuccessfulProcessRunner(?string $sarifContent = null): ProcessRunnerInterface
    {
        return $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(0, '', ''),
            $sarifContent
        );
    }

    public function testGetName(): void
    {
        $checker = $this->createChecker(true, $this->createStub(ProcessRunnerInterface::class));

        $this->assertSame('gitleaks', $checker->getName());
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

    public function testDisabledWhenGitleaksIsMissing(): void
    {
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->once())
            ->method('run')
            ->with(['gitleaks', 'version'])
            ->willReturn(new ProcessResult(127, '', 'gitleaks: command not found'))
        ;
        $checker = $this->createChecker(true, $processRunner);

        $this->assertNull($checker->check($this->createProject()));
    }

    public function testScanFailure(): void
    {
        $processRunner = $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(1, '', 'unexpected failure')
        );
        $checker = $this->createChecker(true, $processRunner);

        $this->assertSame([
            'success' => false,
            'secrets' => false,
            'summary' => false,
        ], $checker->check($this->createProject()));
    }

    public function testMissingReport(): void
    {
        $checker = $this->createChecker(true, $this->createSuccessfulProcessRunner());

        $this->assertSame([
            'success' => false,
            'secrets' => false,
            'summary' => false,
        ], $checker->check($this->createProject()));
    }

    public function testStoreFailure(): void
    {
        $checker = $this->createChecker(
            true,
            $this->createSuccessfulProcessRunner($this->createSarifContent([])),
            $this->createReportStore(writable: false)
        );

        $this->assertSame([
            'success' => false,
            'secrets' => false,
            'summary' => false,
        ], $checker->check($this->createProject()));
    }

    public function testScanWithSecrets(): void
    {
        $processRunner = $this->createSuccessfulProcessRunner($this->createSarifContent([
            'aws-access-token',
            'generic-api-key',
            'aws-access-token',
        ]));
        $checker = $this->createChecker(true, $processRunner);

        $this->assertSame([
            'success' => true,
            'secrets' => [
                'aws-access-token' => 2,
                'generic-api-key' => 1,
            ],
            'summary' => ['count' => 3],
        ], $checker->check($this->createProject()));
    }

    public function testScanWithoutSecret(): void
    {
        $checker = $this->createChecker(
            true,
            $this->createSuccessfulProcessRunner($this->createSarifContent([]))
        );

        $this->assertSame([
            'success' => true,
            'secrets' => [],
            'summary' => ['count' => 0],
        ], $checker->check($this->createProject()));
    }

    public function testReportIsStored(): void
    {
        $sarifContent = $this->createSarifContent(['aws-access-token']);
        $checker = $this->createChecker(true, $this->createSuccessfulProcessRunner($sarifContent));

        $checker->check($this->createProject());

        $this->assertSame(
            ['gitleaks/'.self::PROJECT_ID => $sarifContent],
            $this->storedReports
        );
    }

    public function testAvailabilityIsResolvedOnce(): void
    {
        $sarifContent = $this->createSarifContent(['aws-access-token']);
        $subCommands = [];
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(
                function (array $command) use (&$subCommands, $sarifContent): ProcessResult {
                    $subCommands[] = $command[1];
                    if ('version' === $command[1]) {
                        return new ProcessResult(0, '8.18.4', '');
                    }
                    $this->writeReport($command, $sarifContent);

                    return new ProcessResult(0, '', '');
                }
            )
        ;
        $checker = $this->createChecker(true, $processRunner);

        $project = $this->createProject();
        $checker->check($project);
        $checker->check($project);

        $this->assertSame(['version', 'detect', 'detect'], $subCommands);
    }
}
