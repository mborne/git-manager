<?php

namespace App\Tests\Unit\Git\Checker;

use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\FileReaderInterface;
use MBO\GitManager\Filesystem\LocalFilesystemInterface;
use MBO\GitManager\Git\Checker\Gitleaks\GitleaksRunner;
use MBO\GitManager\Git\Checker\SecretChecker;
use MBO\GitManager\Process\ProcessResult;
use MBO\GitManager\Process\ProcessRunnerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SecretCheckerTest extends TestCase
{
    private const CONFIG_PATH = '/app/config/gitleaks.toml';
    private const REPOSITORY_PATH = '/data/github.com/mborne/demo';
    private const REPORT_PATH = '/data/.gitleaks/demo.sarif';

    private function createProject(): Project
    {
        $project = new Project();
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
        $localFilesystem
            ->method('getSecretReportPath')
            ->willReturn(self::REPORT_PATH)
        ;

        return $localFilesystem;
    }

    /**
     * FileReader providing an optional SARIF report at the expected path.
     */
    private function createFileReader(?string $sarifContent = null): FileReaderInterface
    {
        $files = null === $sarifContent ? [] : [self::REPORT_PATH => $sarifContent];

        $fileReader = $this->createStub(FileReaderInterface::class);
        $fileReader
            ->method('exists')
            ->willReturnCallback(fn (string $path): bool => isset($files[$path]))
        ;
        $fileReader
            ->method('read')
            ->willReturnCallback(fn (string $path): ?string => $files[$path] ?? null)
        ;

        return $fileReader;
    }

    private function createChecker(
        bool $gitleaksEnabled,
        ProcessRunnerInterface $processRunner,
        FileReaderInterface $fileReader,
    ): SecretChecker {
        return new SecretChecker(
            $gitleaksEnabled,
            new GitleaksRunner($processRunner, $fileReader, self::CONFIG_PATH),
            $this->createLocalFilesystem(),
            $fileReader,
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

    public function testGetName(): void
    {
        $checker = $this->createChecker(
            true,
            $this->createStub(ProcessRunnerInterface::class),
            $this->createFileReader()
        );

        $this->assertSame('secret', $checker->getName());
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
        $processRunner = $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(0, '', '')
        );
        $checker = $this->createChecker(true, $processRunner, $this->createFileReader());

        $this->assertSame([
            'success' => false,
            'secrets' => false,
            'summary' => false,
        ], $checker->check($this->createProject()));
    }

    public function testScanWithSecrets(): void
    {
        $processRunner = $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(0, '', '')
        );
        $fileReader = $this->createFileReader($this->createSarifContent([
            'aws-access-token',
            'generic-api-key',
            'aws-access-token',
        ]));
        $checker = $this->createChecker(true, $processRunner, $fileReader);

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
        $processRunner = $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(0, '', '')
        );
        $checker = $this->createChecker(
            true,
            $processRunner,
            $this->createFileReader($this->createSarifContent([]))
        );

        $this->assertSame([
            'success' => true,
            'secrets' => [],
            'summary' => ['count' => 0],
        ], $checker->check($this->createProject()));
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
