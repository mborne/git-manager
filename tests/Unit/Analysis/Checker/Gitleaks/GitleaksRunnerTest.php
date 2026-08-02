<?php

namespace App\Tests\Unit\Analysis\Checker\Gitleaks;

use MBO\GitManager\Analysis\Checker\Gitleaks\GitleaksException;
use MBO\GitManager\Analysis\Checker\Gitleaks\GitleaksRunner;
use MBO\GitManager\Process\ProcessResult;
use MBO\GitManager\Process\ProcessRunnerInterface;
use MBO\GitManager\Storage\TempFilesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class GitleaksRunnerTest extends TestCase
{
    private const SARIF_CONTENT = '{"runs":[]}';

    /**
     * Temporary directory containing the repository and the default config.
     */
    private string $workspace;

    private string $repositoryPath;

    private string $defaultConfigPath;

    private string $repositoryConfigPath;

    /**
     * The commands run by the runner, as [command, workingDirectory] pairs.
     *
     * @var array<int,array{0:string[],1:?string}>
     */
    private array $commands = [];

    protected function setUp(): void
    {
        $this->commands = [];

        $this->workspace = sys_get_temp_dir().'/git-manager-gitleaks-'.uniqid();
        $this->repositoryPath = $this->workspace.'/github.com/mborne/demo';
        (new Filesystem())->mkdir($this->repositoryPath);

        $this->defaultConfigPath = $this->workspace.'/gitleaks.toml';
        $this->repositoryConfigPath = $this->repositoryPath.'/'.GitleaksRunner::REPOSITORY_CONFIG_NAME;
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workspace);
    }

    /**
     * Create an empty gitleaks config file.
     */
    private function createConfig(string $path): void
    {
        (new Filesystem())->dumpFile($path, '');
    }

    /**
     * ProcessRunner recording the commands, answering according to the gitleaks
     * subcommand and writing the given SARIF content to the report path.
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
                    $this->commands[] = [$command, $workingDirectory];
                    if ('version' === $command[1]) {
                        return $version;
                    }
                    if (null !== $sarifContent) {
                        file_put_contents($this->getReportPath(), $sarifContent);
                    }

                    return $detect;
                }
            )
        ;

        return $processRunner;
    }

    /**
     * ProcessRunner answering a successful scan.
     */
    private function createSuccessfulProcessRunner(?string $sarifContent = null): ProcessRunnerInterface
    {
        return $this->createProcessRunner(
            new ProcessResult(0, '8.18.4', ''),
            new ProcessResult(0, '', ''),
            $sarifContent
        );
    }

    private function createRunner(ProcessRunnerInterface $processRunner): GitleaksRunner
    {
        return new GitleaksRunner(
            $processRunner,
            new TempFilesystem(),
            $this->defaultConfigPath
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
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::SARIF_CONTENT));

        $this->assertSame(self::SARIF_CONTENT, $runner->detect($this->repositoryPath));
    }

    public function testDetectUsesATemporaryReportPath(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::SARIF_CONTENT));
        $runner->detect($this->repositoryPath);

        $this->assertMatchesRegularExpression(
            '#^'.preg_quote(sys_get_temp_dir(), '#').'.gitleaks-[0-9a-f]{16}\.sarif$#',
            $this->getReportPath()
        );
    }

    public function testDetectWithoutConfig(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::SARIF_CONTENT));
        $runner->detect($this->repositoryPath);

        $this->assertSame(
            [$this->getExpectedDetectCommand($this->getReportPath()), $this->repositoryPath],
            end($this->commands)
        );
    }

    public function testDetectWithDefaultConfig(): void
    {
        $this->createConfig($this->defaultConfigPath);
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::SARIF_CONTENT));
        $runner->detect($this->repositoryPath);

        $this->assertSame(
            [
                [...$this->getExpectedDetectCommand($this->getReportPath()), '--config', $this->defaultConfigPath],
                $this->repositoryPath,
            ],
            end($this->commands)
        );
    }

    /**
     * A config provided by the repository takes precedence over the default one.
     */
    public function testDetectWithRepositoryConfig(): void
    {
        $this->createConfig($this->defaultConfigPath);
        $this->createConfig($this->repositoryConfigPath);
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::SARIF_CONTENT));
        $runner->detect($this->repositoryPath);

        $this->assertSame(
            [
                [...$this->getExpectedDetectCommand($this->getReportPath()), '--config', $this->repositoryConfigPath],
                $this->repositoryPath,
            ],
            end($this->commands)
        );
    }

    /**
     * The repository config is used even if the default one is missing.
     */
    public function testDetectWithRepositoryConfigOnly(): void
    {
        $this->createConfig($this->repositoryConfigPath);
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::SARIF_CONTENT));
        $runner->detect($this->repositoryPath);

        $this->assertSame(
            [
                [...$this->getExpectedDetectCommand($this->getReportPath()), '--config', $this->repositoryConfigPath],
                $this->repositoryPath,
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
        $runner = $this->createRunner($processRunner);

        $this->expectException(GitleaksException::class);
        $this->expectExceptionMessage('unexpected failure');
        $runner->detect($this->repositoryPath);
    }

    public function testDetectMissingReport(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner());

        $this->expectException(GitleaksException::class);
        $this->expectExceptionMessage('gitleaks report not found');
        $runner->detect($this->repositoryPath);
    }

    /**
     * The temporary report must not be left behind.
     */
    public function testDetectRemovesTheTemporaryReport(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::SARIF_CONTENT));

        $this->assertSame(self::SARIF_CONTENT, $runner->detect($this->repositoryPath));
        $this->assertFileDoesNotExist($this->getReportPath());
    }
}
