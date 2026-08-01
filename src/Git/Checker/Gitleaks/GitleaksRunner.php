<?php

namespace MBO\GitManager\Git\Checker\Gitleaks;

use MBO\GitManager\Filesystem\FileReaderInterface;
use MBO\GitManager\Process\ProcessRunnerInterface;

/**
 * Wrapper around the gitleaks command line.
 */
final class GitleaksRunner
{
    public const BINARY = 'gitleaks';

    public function __construct(
        private ProcessRunnerInterface $processRunner,
        private FileReaderInterface $fileReader,
        private string $gitleaksConfigPath,
    ) {
    }

    /**
     * Get gitleaks version.
     *
     * @throws GitleaksException if the gitleaks executable is not available
     */
    public function getVersion(): string
    {
        $result = $this->processRunner->run([self::BINARY, 'version']);
        if (!$result->isSuccessful()) {
            throw new GitleaksException(sprintf('fail to get gitleaks version : %s', trim($result->errorOutput)));
        }

        return trim($result->output);
    }

    /**
     * Test if the gitleaks executable is available.
     */
    public function isAvailable(): bool
    {
        try {
            $this->getVersion();

            return true;
        } catch (GitleaksException) {
            return false;
        }
    }

    /**
     * Scan a repository producing a SARIF report.
     *
     * @throws GitleaksException if the scan fails or if the report is not produced
     */
    public function detect(string $repositoryPath, string $reportPath): void
    {
        $command = [
            self::BINARY,
            'detect',
            '--source', '.',
            '--report-format', 'sarif',
            '--report-path', $reportPath,
            '--no-git',
            '--exit-code', '0',
        ];
        if ($this->fileReader->exists($this->gitleaksConfigPath)) {
            $command[] = '--config';
            $command[] = $this->gitleaksConfigPath;
        }

        $result = $this->processRunner->run($command, $repositoryPath);
        if (!$result->isSuccessful()) {
            throw new GitleaksException(sprintf('gitleaks scan failed on %s : %s', $repositoryPath, trim($result->errorOutput)));
        }

        if (!$this->fileReader->exists($reportPath)) {
            throw new GitleaksException(sprintf('gitleaks report not found : %s', $reportPath));
        }
    }
}
