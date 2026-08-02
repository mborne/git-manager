<?php

namespace MBO\GitManager\Analysis\Checker\Gitleaks;

use MBO\GitManager\Process\ProcessRunnerInterface;
use MBO\GitManager\Storage\TempFilesystem;

/**
 * Wrapper around the gitleaks command line.
 */
final class GitleaksRunner
{
    public const BINARY = 'gitleaks';

    /**
     * Name of the gitleaks config file that may be provided by a repository.
     */
    public const REPOSITORY_CONFIG_NAME = '.gitleaks.toml';

    public function __construct(
        private ProcessRunnerInterface $processRunner,
        private TempFilesystem $tempFilesystem,
        private string $gitleaksDefaultConfigPath,
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
     * Scan a repository and get the content of the resulting SARIF report.
     *
     * Note that gitleaks writes its report to a file, hence the temporary file
     * removed once its content has been read.
     *
     * @throws GitleaksException if the scan fails or if the report is not produced
     */
    public function detect(string $repositoryPath): string
    {
        $reportPath = $this->tempFilesystem->getPath('gitleaks', 'sarif');
        try {
            return $this->runDetect($repositoryPath, $reportPath);
        } finally {
            $this->tempFilesystem->remove($reportPath);
        }
    }

    /**
     * Run "gitleaks detect" and read the report it produces.
     *
     * @throws GitleaksException if the scan fails or if the report is not produced
     */
    private function runDetect(string $repositoryPath, string $reportPath): string
    {
        $command = [
            self::BINARY,
            'detect',
            '--source', '.',
            '--report-format', 'sarif',
            '--report-path', $reportPath,
            '--exit-code', '0',
        ];
        $configPath = $this->getConfigPath($repositoryPath);
        if (null !== $configPath) {
            $command[] = '--config';
            $command[] = $configPath;
        }

        $result = $this->processRunner->run($command, $repositoryPath);
        if (!$result->isSuccessful()) {
            throw new GitleaksException(sprintf('gitleaks scan failed on %s : %s', $repositoryPath, trim($result->errorOutput)));
        }

        $content = is_file($reportPath) ? file_get_contents($reportPath) : false;
        if (false === $content) {
            throw new GitleaksException(sprintf('gitleaks report not found : %s', $reportPath));
        }

        return $content;
    }

    /**
     * Get the config to use for a repository : the one provided by the repository
     * if any, the default one otherwise (null if neither is available).
     */
    private function getConfigPath(string $repositoryPath): ?string
    {
        $repositoryConfigPath = $repositoryPath.'/'.self::REPOSITORY_CONFIG_NAME;
        if (file_exists($repositoryConfigPath)) {
            return $repositoryConfigPath;
        }

        return file_exists($this->gitleaksDefaultConfigPath) ? $this->gitleaksDefaultConfigPath : null;
    }
}
