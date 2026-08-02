<?php

namespace App\Tests\Analysis\Checker\Trivy;

use MBO\GitManager\Analysis\Checker\Trivy\TrivyException;
use MBO\GitManager\Analysis\Checker\Trivy\TrivyRunner;
use MBO\GitManager\Process\ProcessResult;
use MBO\GitManager\Process\ProcessRunnerInterface;
use MBO\GitManager\Storage\TempFilesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class TrivyRunnerTest extends TestCase
{
    private const REPOSITORY_PATH = '/data/github.com/mborne/demo';
    private const JSON_CONTENT = '{"Results":[]}';
    private const VERSION_OUTPUT = 'Version: 0.58.1';

    /**
     * Temporary directory containing the trivy config.
     */
    private string $workspace;

    private string $configPath;

    /**
     * The commands run by the runner, as [command, workingDirectory] pairs.
     *
     * @var array<int,array{0:string[],1:?string}>
     */
    private array $commands = [];

    protected function setUp(): void
    {
        $this->commands = [];

        $this->workspace = sys_get_temp_dir().'/git-manager-trivy-'.uniqid();
        $this->configPath = $this->workspace.'/trivy.yaml';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workspace);
    }

    /**
     * Create an empty trivy config file.
     */
    private function createConfig(): void
    {
        (new Filesystem())->dumpFile($this->configPath, '');
    }

    /**
     * ProcessRunner recording the commands, answering according to the trivy
     * subcommand and writing the given JSON content to the report path.
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
                function (array $command, ?string $workingDirectory = null) use ($version, $scan, $jsonContent): ProcessResult {
                    $this->commands[] = [$command, $workingDirectory];
                    if ('--version' === $command[1]) {
                        return $version;
                    }
                    if (null !== $jsonContent) {
                        file_put_contents($this->getReportPath(), $jsonContent);
                    }

                    return $scan;
                }
            )
        ;

        return $processRunner;
    }

    /**
     * ProcessRunner answering a successful scan.
     */
    private function createSuccessfulProcessRunner(?string $jsonContent = null): ProcessRunnerInterface
    {
        return $this->createProcessRunner(
            new ProcessResult(0, self::VERSION_OUTPUT, ''),
            new ProcessResult(0, '', ''),
            $jsonContent
        );
    }

    private function createRunner(ProcessRunnerInterface $processRunner): TrivyRunner
    {
        return new TrivyRunner(
            $processRunner,
            new TempFilesystem(),
            $this->configPath
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
            '--severity', 'HIGH,CRITICAL,MEDIUM',
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
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::JSON_CONTENT));

        $this->assertSame(self::JSON_CONTENT, $runner->scan(self::REPOSITORY_PATH));
    }

    public function testScanUsesATemporaryReportPath(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::JSON_CONTENT));
        $runner->scan(self::REPOSITORY_PATH);

        $this->assertMatchesRegularExpression(
            '#^'.preg_quote(sys_get_temp_dir(), '#').'.trivy-[0-9a-f]{16}\.json$#',
            $this->getReportPath()
        );
    }

    public function testScanWithoutConfig(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::JSON_CONTENT));
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
        $this->createConfig();
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::JSON_CONTENT));
        $runner->scan(self::REPOSITORY_PATH);

        $this->assertSame(
            [
                [
                    ...$this->getExpectedScanCommand($this->getReportPath()),
                    '--config', $this->configPath,
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
        $runner = $this->createRunner($processRunner);

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
     * The temporary report must not be left behind.
     */
    public function testScanRemovesTheTemporaryReport(): void
    {
        $runner = $this->createRunner($this->createSuccessfulProcessRunner(self::JSON_CONTENT));

        $this->assertSame(self::JSON_CONTENT, $runner->scan(self::REPOSITORY_PATH));
        $this->assertFileDoesNotExist($this->getReportPath());
    }
}
