<?php

namespace App\Tests\Unit\Analysis\Checker\Gitleaks;

use MBO\GitManager\Analysis\Checker\Gitleaks\GitleaksException;
use MBO\GitManager\Analysis\Checker\Gitleaks\GitleaksRunner;
use MBO\GitManager\Filesystem\FileReaderInterface;
use MBO\GitManager\Filesystem\LocalFileReader;
use MBO\GitManager\Process\ProcessResult;
use MBO\GitManager\Process\ProcessRunnerInterface;
use MBO\GitManager\Storage\TempFilesystem;
use PHPUnit\Framework\TestCase;

final class GitleaksRunnerTest extends TestCase
{
    private const DEFAULT_CONFIG_PATH = '/app/config/gitleaks.toml';
    private const REPOSITORY_PATH = '/data/github.com/mborne/demo';
    private const REPOSITORY_CONFIG_PATH = self::REPOSITORY_PATH.'/.gitleaks.toml';
    private const SARIF_CONTENT = '{"runs":[]}';

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
     * FileReader providing the given existing gitleaks configs and reporting the
     * same SARIF content whatever the temporary report path is.
     *
     * @param string[] $existingConfigPaths
     */
    private function createFileReader(?string $sarifContent = null, array $existingConfigPaths = []): FileReaderInterface
    {
        $fileReader = $this->createStub(FileReaderInterface::class);
        $fileReader
            ->method('exists')
            ->willReturnCallback(fn (string $path): bool => in_array($path, $existingConfigPaths, true))
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
     * ProcessRunner recording the commands and answering according to the
     * gitleaks subcommand.
     */
    private function createProcessRunner(ProcessResult $version, ProcessResult $detect): ProcessRunnerInterface
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(
                function (array $command, ?string $workingDirectory = null) use ($version, $detect): ProcessResult {
                    $this->commands[] = [$command, $workingDirectory];

                    return 'version' === $command[1] ? $version : $detect;
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
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(0, '', '')
        );
    }

    private function createRunner(
        ProcessRunnerInterface $processRunner,
        ?FileReaderInterface $fileReader = null,
    ): GitleaksRunner {
        return new GitleaksRunner(
            $processRunner,
            $fileReader ?? $this->createFileReader(),
            new TempFilesystem(),
            self::DEFAULT_CONFIG_PATH
        );
    }

    /**
     * Get the temporary report path of the last "gitleaks detect" command.
     */
    private function getReportPath(): string
    {
        $command = end($this->commands)[0];
        $index = array_search('--report-path', $command, true);
        $this->assertIsInt($index);

        return $command[$index + 1];
    }

    /**
     * Get the expected "gitleaks detect" command for a given report path.
     *
     * @return string[]
     */
    private function getExpectedDetectCommand(string $reportPath): array
    {
        return [
            'gitleaks',
            'detect',
            '--source', '.',
            '--report-format', 'sarif',
            '--report-path', $reportPath,
            '--exit-code', '0',
        ];
    }

    public function testGetVersion(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner());

        $this->assertSame('8.18.4', $runner->getVersion());
        $this->assertSame([[['gitleaks', 'version'], null]], $this->commands);
    }

    public function testGetVersionFailure(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(127, '', 'gitleaks: command not found'))
        ;
        $runner = $this->createRunner($processRunner);

        $this->expectException(GitleaksException::class);
        $this->expectExceptionMessage('gitleaks: command not found');
        $runner->getVersion();
    }

    public function testIsAvailable(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner());

        $this->assertTrue($runner->isAvailable());
    }

    public function testIsAvailableWhenGitleaksIsMissing(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(127, '', 'gitleaks: command not found'))
        ;
        $runner = $this->createRunner($processRunner);

        $this->assertFalse($runner->isAvailable());
    }

    public function testDetectReturnsTheReportContent(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::SARIF_CONTENT)
        );

        $this->assertSame(self::SARIF_CONTENT, $runner->detect(self::REPOSITORY_PATH));
    }

    public function testDetectUsesATemporaryReportPath(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::SARIF_CONTENT)
        );
        $runner->detect(self::REPOSITORY_PATH);

        $this->assertMatchesRegularExpression(
            '#^'.preg_quote(sys_get_temp_dir(), '#').'.gitleaks-[0-9a-f]{16}\.sarif$#',
            $this->getReportPath()
        );
    }

    public function testDetectWithoutConfig(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::SARIF_CONTENT)
        );
        $runner->detect(self::REPOSITORY_PATH);

        $this->assertSame(
            [$this->getExpectedDetectCommand($this->getReportPath()), self::REPOSITORY_PATH],
            end($this->commands)
        );
    }

    public function testDetectWithDefaultConfig(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::SARIF_CONTENT, [self::DEFAULT_CONFIG_PATH])
        );
        $runner->detect(self::REPOSITORY_PATH);

        $this->assertSame(
            [
                [...$this->getExpectedDetectCommand($this->getReportPath()), '--config', self::DEFAULT_CONFIG_PATH],
                self::REPOSITORY_PATH,
            ],
            end($this->commands)
        );
    }

    /**
     * A config provided by the repository takes precedence over the default one.
     */
    public function testDetectWithRepositoryConfig(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::SARIF_CONTENT, [self::DEFAULT_CONFIG_PATH, self::REPOSITORY_CONFIG_PATH])
        );
        $runner->detect(self::REPOSITORY_PATH);

        $this->assertSame(
            [
                [...$this->getExpectedDetectCommand($this->getReportPath()), '--config', self::REPOSITORY_CONFIG_PATH],
                self::REPOSITORY_PATH,
            ],
            end($this->commands)
        );
    }

    /**
     * The repository config is used even if the default one is missing.
     */
    public function testDetectWithRepositoryConfigOnly(): void
    {
        $runner = $this->createRunner(
            $this->createSuccessfulProcessRunner(),
            $this->createFileReader(self::SARIF_CONTENT, [self::REPOSITORY_CONFIG_PATH])
        );
        $runner->detect(self::REPOSITORY_PATH);

        $this->assertSame(
            [
                [...$this->getExpectedDetectCommand($this->getReportPath()), '--config', self::REPOSITORY_CONFIG_PATH],
                self::REPOSITORY_PATH,
            ],
            end($this->commands)
        );
    }

    public function testDetectProcessFailure(): void
    {
        $processRunner = $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(1, '', 'unexpected failure')
        );
        $runner = $this->createRunner($processRunner, $this->createFileReader(self::SARIF_CONTENT));

        $this->expectException(GitleaksException::class);
        $this->expectExceptionMessage('unexpected failure');
        $runner->detect(self::REPOSITORY_PATH);
    }

    public function testDetectMissingReport(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner());

        $this->expectException(GitleaksException::class);
        $this->expectExceptionMessage('gitleaks report not found');
        $runner->detect(self::REPOSITORY_PATH);
    }

    /**
     * The temporary report must not be left behind, hence a scan against a real
     * temporary file written by the process.
     */
    public function testDetectRemovesTheTemporaryReport(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(
                function (array $command, ?string $workingDirectory = null): ProcessResult {
                    $this->commands[] = [$command, $workingDirectory];
                    file_put_contents($this->getReportPath(), self::SARIF_CONTENT);

                    return new ProcessResult(0, '', '');
                }
            )
        ;
        $runner = new GitleaksRunner(
            $processRunner,
            new LocalFileReader(),
            new TempFilesystem(),
            self::DEFAULT_CONFIG_PATH
        );

        $this->assertSame(self::SARIF_CONTENT, $runner->detect(self::REPOSITORY_PATH));
        $this->assertFileDoesNotExist($this->getReportPath());
    }
}
