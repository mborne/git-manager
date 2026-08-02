<?php

namespace App\Tests\Unit\Analysis\Checker;

use MBO\GitManager\Analysis\Checker\Gitleaks\GitleaksRunner;
use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\FileReaderInterface;
use MBO\GitManager\Filesystem\LocalFilesystemInterface;
use MBO\GitManager\Filesystem\TempFilesystem;
use MBO\GitManager\Process\ProcessResult;
use MBO\GitManager\Process\ProcessRunnerInterface;
use MBO\GitManager\Storage\ReportStoreException;
use MBO\GitManager\Storage\ReportStoreInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

final class GitleaksCheckerTest extends TestCase
{
    private const DEFAULT_CONFIG_PATH = '/app/config/gitleaks.toml';
    private const REPOSITORY_PATH = '/data/github.com/mborne/demo';
    private const PROJECT_ID = '0b7b2b2e-1e2a-3d4f-8a9b-0c1d2e3f4a5b';

    /**
     * The reports written to the store, indexed by "{toolName}/{projectId}".
     *
     * @var array<string,string>
     */
    private array $storedReports = [];

    protected function setUp(): void
    {
        $this->storedReports = [];
    }

    private function createProject(): Project
    {
        $project = new Project();
        $project->setId(Uuid::fromString(self::PROJECT_ID));
        $project->setFullName('github.com/mborne/demo');

        return $project;
    }

    private function createLocalFilesystem(): LocalFilesystemInterface
    {
        $localFilesystem = $this->createStub(LocalFilesystemInterface::class);
        $localFilesystem
            ->method('getGitRepositoryPath')
            ->willReturn(self::REPOSITORY_PATH)
        ;

        return $localFilesystem;
    }

    /**
     * FileReader reporting the same SARIF content whatever the temporary
     * report path is.
     */
    private function createFileReader(?string $sarifContent = null): FileReaderInterface
    {
        $fileReader = $this->createStub(FileReaderInterface::class);
        $fileReader
            ->method('exists')
            ->willReturn(false)
        ;
        $fileReader
            ->method('read')
            ->willReturnCallback(
                fn (string $path): ?string => str_ends_with($path, '.sarif') ? $sarifContent : null
            )
        ;

        return $fileReader;
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
        FileReaderInterface $fileReader,
        ?ReportStoreInterface $reportStore = null,
    ): GitleaksChecker {
        return new GitleaksChecker(
            $gitleaksEnabled,
            new GitleaksRunner($processRunner, $fileReader, new TempFilesystem(), self::DEFAULT_CONFIG_PATH),
            $this->createLocalFilesystem(),
            $reportStore ?? $this->createReportStore(),
            new NullLogger()
        );
    }

    /**
     * ProcessRunner answering according to the gitleaks subcommand.
     */
    private function createProcessRunner(ProcessResult $version, ProcessResult $detect): ProcessRunnerInterface
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(
                fn (array $command): ProcessResult => 'version' === $command[1] ? $version : $detect
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
    private function createSuccessfulProcessRunner(): ProcessRunnerInterface
    {
        return $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(0, '', '')
        );
    }

    public function testGetName(): void
    {
        $checker = $this->createChecker(
            true,
            $this->createStub(ProcessRunnerInterface::class),
            $this->createFileReader()
        );

        $this->assertSame('gitleaks', $checker->getName());
    }

    public function testDisabledByConfiguration(): void
    {
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->never())
            ->method('run')
        ;
        $checker = $this->createChecker(false, $processRunner, $this->createFileReader());

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
        $checker = $this->createChecker(true, $processRunner, $this->createFileReader());

        $this->assertNull($checker->check($this->createProject()));
    }

    public function testScanFailure(): void
    {
        $processRunner = $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(1, '', 'unexpected failure')
        );
        $checker = $this->createChecker(true, $processRunner, $this->createFileReader());

        $this->assertSame([
            'success' => false,
            'secrets' => false,
            'summary' => false,
        ], $checker->check($this->createProject()));
    }

    public function testMissingReport(): void
    {
        $checker = $this->createChecker(
            true,
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader()
        );

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
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader($this->createSarifContent([])),
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
        $fileReader = $this->createFileReader($this->createSarifContent([
            'aws-access-token',
            'generic-api-key',
            'aws-access-token',
        ]));
        $checker = $this->createChecker(true, $this->createSuccessfulProcessRunner(), $fileReader);

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
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader($this->createSarifContent([]))
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
        $checker = $this->createChecker(
            true,
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader($sarifContent)
        );

        $checker->check($this->createProject());

        $this->assertSame(
            ['gitleaks/'.self::PROJECT_ID => $sarifContent],
            $this->storedReports
        );
    }

    public function testAvailabilityIsResolvedOnce(): void
    {
        $subCommands = [];
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(
                function (array $command) use (&$subCommands): ProcessResult {
                    $subCommands[] = $command[1];

                    return 'version' === $command[1]
                        ? new ProcessResult(0, '8.18.4', '')
                        : new ProcessResult(0, '', '');
                }
            )
        ;
        $fileReader = $this->createFileReader($this->createSarifContent(['aws-access-token']));
        $checker = $this->createChecker(true, $processRunner, $fileReader);

        $project = $this->createProject();
        $checker->check($project);
        $checker->check($project);

        $this->assertSame(['version', 'detect', 'detect'], $subCommands);
    }
}
