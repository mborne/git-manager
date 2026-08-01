<?php

namespace App\Tests\Unit\Analysis\Checker\Trivy;

use MBO\GitManager\Analysis\Checker\Trivy\TrivyException;
use MBO\GitManager\Analysis\Checker\Trivy\TrivyRunner;
use MBO\GitManager\Filesystem\FileReaderInterface;
use MBO\GitManager\Filesystem\LocalFileReader;
use MBO\GitManager\Filesystem\TempFilesystem;
use MBO\GitManager\Process\ProcessResult;
use MBO\GitManager\Process\ProcessRunnerInterface;
use PHPUnit\Framework\TestCase;

final class TrivyRunnerTest extends TestCase
{
    private const CONFIG_PATH = '/app/config/trivy.yaml';
    private const REPOSITORY_PATH = '/data/github.com/mborne/demo';
    private const JSON_CONTENT = '{"Results":[]}';
    private const VERSION_OUTPUT = 'Version: 0.58.1';

    /**
     * The commands run by the runner, as [command, workingDirectory] pairs.
     *
     * @var array<int,array{0:string[],1:?string}>
     */
    private array $commands = [];

    protected function setUp(): void
    {
        $this->commands = [];
    }

    /**
     * FileReader providing an optional trivy config and reporting the same
     * JSON content whatever the temporary report path is.
     */
    private function createFileReader(?string $jsonContent = null, bool $withConfig = false): FileReaderInterface
    {
        $fileReader = $this->createStub(FileReaderInterface::class);
        $fileReader
            ->method('exists')
            ->willReturnCallback(fn (string $path): bool => $withConfig && self::CONFIG_PATH === $path)
        ;
        $fileReader
            ->method('read')
            ->willReturnCallback(
                fn (string $path): ?string => str_ends_with($path, '.json') ? $jsonContent : null
            )
        ;

        return $fileReader;
    }

    /**
     * ProcessRunner recording the commands and answering according to the
     * trivy subcommand.
     */
    private function createProcessRunner(ProcessResult $version, ProcessResult $scan): ProcessRunnerInterface
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(
                function (array $command, ?string $workingDirectory = null) use ($version, $scan): ProcessResult {
                    $this->commands[] = [$command, $workingDirectory];

                    return '--version' === $command[1] ? $version : $scan;
                }
            )
        ;

        return $processRunner;
    }

    /**
     * ProcessRunner answering a successful scan.
     */
    private function createSuccessfulProcessRunner(): ProcessRunnerInterface
    {
        return $this->createProcessRunner(
            new ProcessResult(0, self::VERSION_OUTPUT, ''),
            new ProcessResult(0, '', '')
        );
    }

    private function createRunner(
        ProcessRunnerInterface $processRunner,
        ?FileReaderInterface $fileReader = null,
    ): TrivyRunner {
        return new TrivyRunner(
            $processRunner,
            $fileReader ?? $this->createFileReader(),
            new TempFilesystem(),
            self::CONFIG_PATH
        );
    }

    /**
     * Get the temporary report path of the last "trivy fs" command.
     */
    private function getReportPath(): string
    {
        $command = end($this->commands)[0];
        $index = array_search('--output', $command, true);
        $this->assertIsInt($index);

        return $command[$index + 1];
    }

    /**
     * Get the expected "trivy fs" command for a given report path.
     *
     * @return string[]
     */
    private function getExpectedScanCommand(string $reportPath): array
    {
        return [
            'trivy',
            'fs',
            '--scanners', 'vuln',
            '--severity', 'HIGH,CRITICAL',
            '--offline-scan',
            '--format', 'json',
            '--output', $reportPath,
        ];
    }

    public function testGetVersion(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner());

        $this->assertSame(self::VERSION_OUTPUT, $runner->getVersion());
        $this->assertSame([[['trivy', '--version'], null]], $this->commands);
    }

    public function testGetVersionFailure(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(127, '', 'trivy: command not found'))
        ;
        $runner = $this->createRunner($processRunner);

        $this->expectException(TrivyException::class);
        $this->expectExceptionMessage('trivy: command not found');
        $runner->getVersion();
    }

    public function testIsAvailable(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner());

        $this->assertTrue($runner->isAvailable());
    }

    public function testIsAvailableWhenTrivyIsMissing(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(127, '', 'trivy: command not found'))
        ;
        $runner = $this->createRunner($processRunner);

        $this->assertFalse($runner->isAvailable());
    }

    public function testScanReturnsTheReportContent(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::JSON_CONTENT)
        );

        $this->assertSame(self::JSON_CONTENT, $runner->scan(self::REPOSITORY_PATH));
    }

    public function testScanUsesATemporaryReportPath(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::JSON_CONTENT)
        );
        $runner->scan(self::REPOSITORY_PATH);

        $this->assertMatchesRegularExpression(
            '#^'.preg_quote(sys_get_temp_dir(), '#').'.trivy-[0-9a-f]{16}\.json$#',
            $this->getReportPath()
        );
    }

    public function testScanWithoutConfig(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::JSON_CONTENT)
        );
        $runner->scan(self::REPOSITORY_PATH);

        $this->assertSame(
            [
                [...$this->getExpectedScanCommand($this->getReportPath()), self::REPOSITORY_PATH],
                null,
            ],
            end($this->commands)
        );
    }

    public function testScanWithConfig(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::JSON_CONTENT, withConfig: true)
        );
        $runner->scan(self::REPOSITORY_PATH);

        $this->assertSame(
            [
                [
                    ...$this->getExpectedScanCommand($this->getReportPath()),
                    '--config', self::CONFIG_PATH,
                    self::REPOSITORY_PATH,
                ],
                null,
            ],
            end($this->commands)
        );
    }

    public function testScanProcessFailure(): void
    {
        $processRunner = $this->createProcessRunner(
            new ProcessResult(0, self::VERSION_OUTPUT, ''),
            new ProcessResult(1, '', 'unexpected failure')
        );
        $runner = $this->createRunner($processRunner, $this->createFileReader(self::JSON_CONTENT));

        $this->expectException(TrivyException::class);
        $this->expectExceptionMessage('unexpected failure');
        $runner->scan(self::REPOSITORY_PATH);
    }

    public function testScanMissingReport(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner());

        $this->expectException(TrivyException::class);
        $this->expectExceptionMessage('trivy report not found');
        $runner->scan(self::REPOSITORY_PATH);
    }

    /**
     * The temporary report must not be left behind, hence a scan against a real
     * temporary file written by the process.
     */
    public function testScanRemovesTheTemporaryReport(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(
                function (array $command, ?string $workingDirectory = null): ProcessResult {
                    $this->commands[] = [$command, $workingDirectory];
                    file_put_contents($this->getReportPath(), self::JSON_CONTENT);

                    return new ProcessResult(0, '', '');
                }
            )
        ;
        $runner = new TrivyRunner(
            $processRunner,
            new LocalFileReader(),
            new TempFilesystem(),
            self::CONFIG_PATH
        );

        $this->assertSame(self::JSON_CONTENT, $runner->scan(self::REPOSITORY_PATH));
        $this->assertFileDoesNotExist($this->getReportPath());
    }
}
