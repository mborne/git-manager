<?php

namespace MBO\GitManager\Analysis\Checker\Trivy;

use MBO\GitManager\Filesystem\FileReaderInterface;
use MBO\GitManager\Filesystem\TempFilesystem;
use MBO\GitManager\Process\ProcessRunnerInterface;

/**
 * Wrapper around the trivy command line.
 */
final class TrivyRunner
{
    public const BINARY = 'trivy';

    /**
     * The severities reported by the scans.
     */
    public const SEVERITIES = ['HIGH', 'CRITICAL'];

    public function __construct(
        private ProcessRunnerInterface $processRunner,
        private FileReaderInterface $fileReader,
        private TempFilesystem $tempFilesystem,
        private string $trivyConfigPath,
    ) {
    }

    /**
     * Get trivy version.
     *
     * @throws TrivyException if the trivy executable is not available
     */
    public function getVersion(): string
    {
        $result = $this->processRunner->run([self::BINARY, '--version']);
        if (!$result->isSuccessful()) {
            throw new TrivyException(sprintf('fail to get trivy version : %s', trim($result->errorOutput)));
        }

        return trim($result->output);
    }

    /**
     * Test if the trivy executable is available.
     */
    public function isAvailable(): bool
    {
        try {
            $this->getVersion();

            return true;
        } catch (TrivyException) {
            return false;
        }
    }

    /**
     * Scan a repository and get the content of the resulting JSON report.
     *
     * Note that trivy writes its report to a file, hence the temporary file
     * removed once its content has been read.
     *
     * @throws TrivyException if the scan fails or if the report is not produced
     */
    public function scan(string $repositoryPath): string
    {
        $reportPath = $this->tempFilesystem->getPath('trivy', 'json');
        try {
            return $this->runScan($repositoryPath, $reportPath);
        } finally {
            $this->tempFilesystem->remove($reportPath);
        }
    }

    /**
     * Run "trivy fs" and read the report it produces.
     *
     * @throws TrivyException if the scan fails or if the report is not produced
     */
    private function runScan(string $repositoryPath, string $reportPath): string
    {
        $command = [
            self::BINARY,
            'fs',
            '--scanners', 'vuln',
            '--severity', implode(',', self::SEVERITIES),
            '--offline-scan',
            '--format', 'json',
            '--output', $reportPath,
        ];
        if ($this->fileReader->exists($this->trivyConfigPath)) {
            $command[] = '--config';
            $command[] = $this->trivyConfigPath;
        }
        $command[] = $repositoryPath;

        $result = $this->processRunner->run($command);
        if (!$result->isSuccessful()) {
            throw new TrivyException(sprintf('trivy scan failed on %s : %s', $repositoryPath, trim($result->errorOutput)));
        }

        $content = $this->fileReader->read($reportPath);
        if (null === $content) {
            throw new TrivyException(sprintf('trivy report not found : %s', $reportPath));
        }

        return $content;
    }
}
