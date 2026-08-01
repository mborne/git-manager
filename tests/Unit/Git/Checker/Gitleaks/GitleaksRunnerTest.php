<?php

namespace App\Tests\Unit\Git\Checker\Gitleaks;

use MBO\GitManager\Filesystem\FileReaderInterface;
use MBO\GitManager\Git\Checker\Gitleaks\GitleaksException;
use MBO\GitManager\Git\Checker\Gitleaks\GitleaksRunner;
use MBO\GitManager\Process\ProcessResult;
use MBO\GitManager\Process\ProcessRunnerInterface;
use PHPUnit\Framework\TestCase;

final class GitleaksRunnerTest extends TestCase
{
    private const CONFIG_PATH = '/app/config/gitleaks.toml';
    private const REPOSITORY_PATH = '/data/github.com/mborne/demo';
    private const REPORT_PATH = '/data/.gitleaks/demo.sarif';

    /**
     * The expected gitleaks command, without the optional --config option.
     */
    private const DETECT_COMMAND = [
        'gitleaks',
        'detect',
        '--source', '.',
        '--report-format', 'sarif',
        '--report-path', self::REPORT_PATH,
        '--no-git',
        '--exit-code', '0',
    ];

    /**
     * FileReader backed by a path => content map.
     *
     * @param array<string,string> $files
     */
    private function createFileReader(array $files = []): FileReaderInterface
    {
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

    public function testGetVersion(): void
    {
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->once())
            ->method('run')
            ->with(['gitleaks', 'version'])
            ->willReturn(new ProcessResult(0, "8.18.4\n", ''))
        ;
        $runner = new GitleaksRunner($processRunner, $this->createFileReader(), self::CONFIG_PATH);

        $this->assertSame('8.18.4', $runner->getVersion());
    }

    public function testGetVersionFailure(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(127, '', 'gitleaks: command not found'))
        ;
        $runner = new GitleaksRunner($processRunner, $this->createFileReader(), self::CONFIG_PATH);

        $this->expectException(GitleaksException::class);
        $this->expectExceptionMessage('gitleaks: command not found');
        $runner->getVersion();
    }

    public function testIsAvailable(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(0, '8.18.4', ''))
        ;
        $runner = new GitleaksRunner($processRunner, $this->createFileReader(), self::CONFIG_PATH);

        $this->assertTrue($runner->isAvailable());
    }

    public function testIsAvailableWhenGitleaksIsMissing(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(127, '', 'gitleaks: command not found'))
        ;
        $runner = new GitleaksRunner($processRunner, $this->createFileReader(), self::CONFIG_PATH);

        $this->assertFalse($runner->isAvailable());
    }

    public function testDetectWithConfig(): void
    {
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->once())
            ->method('run')
            ->with(
                [...self::DETECT_COMMAND, '--config', self::CONFIG_PATH],
                self::REPOSITORY_PATH
            )
            ->willReturn(new ProcessResult(0, '', ''))
        ;
        $fileReader = $this->createFileReader([
            self::CONFIG_PATH => '[extend]',
            self::REPORT_PATH => '{}',
        ]);

        $runner = new GitleaksRunner($processRunner, $fileReader, self::CONFIG_PATH);
        $runner->detect(self::REPOSITORY_PATH, self::REPORT_PATH);
    }

    public function testDetectWithoutConfig(): void
    {
        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner
            ->expects($this->once())
            ->method('run')
            ->with(self::DETECT_COMMAND, self::REPOSITORY_PATH)
            ->willReturn(new ProcessResult(0, '', ''))
        ;
        $fileReader = $this->createFileReader([self::REPORT_PATH => '{}']);

        $runner = new GitleaksRunner($processRunner, $fileReader, self::CONFIG_PATH);
        $runner->detect(self::REPOSITORY_PATH, self::REPORT_PATH);
    }

    public function testDetectProcessFailure(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(1, '', 'unexpected failure'))
        ;
        $fileReader = $this->createFileReader([self::REPORT_PATH => '{}']);
        $runner = new GitleaksRunner($processRunner, $fileReader, self::CONFIG_PATH);

        $this->expectException(GitleaksException::class);
        $this->expectExceptionMessage('unexpected failure');
        $runner->detect(self::REPOSITORY_PATH, self::REPORT_PATH);
    }

    public function testDetectMissingReport(): void
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner
            ->method('run')
            ->willReturn(new ProcessResult(0, '', ''))
        ;
        $runner = new GitleaksRunner($processRunner, $this->createFileReader(), self::CONFIG_PATH);

        $this->expectException(GitleaksException::class);
        $this->expectExceptionMessage('gitleaks report not found');
        $runner->detect(self::REPOSITORY_PATH, self::REPORT_PATH);
    }
}
