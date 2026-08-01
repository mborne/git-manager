<?php

namespace MBO\GitManager\Git\Checker;

use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\LocalFilesystemInterface;
use MBO\GitManager\Git\Checker\Gitleaks\GitleaksException;
use MBO\GitManager\Git\Checker\Gitleaks\GitleaksRunner;
use MBO\GitManager\Git\Checker\Gitleaks\SarifReport;
use MBO\GitManager\Git\CheckerInterface;
use MBO\GitManager\Storage\ReportStoreException;
use MBO\GitManager\Storage\ReportStoreInterface;
use Psr\Log\LoggerInterface;

/**
 * Perform a scan with gitleaks producing a SARIF report.
 */
final class SecretChecker implements CheckerInterface
{
    /**
     * Name of the tool under which the reports are stored.
     */
    public const TOOL_NAME = 'gitleaks';

    /**
     * Availability of gitleaks, resolved on the first check.
     */
    private ?bool $enabled = null;

    public function __construct(
        private bool $gitleaksEnabled,
        private GitleaksRunner $gitleaksRunner,
        private LocalFilesystemInterface $localFilesystem,
        private ReportStoreInterface $reportStore,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'secret';
    }

    /**
     * @return array{success:bool,secrets:array<string,int>|false,summary:array{count:int}|false}|null
     */
    public function check(Project $project): ?array
    {
        if (!$this->isEnabled()) {
            $this->logger->debug('[{checker}] skipped (disabled)', [
                'checker' => $this->getName(),
                'repository' => $project->getFullName(),
            ]);

            return null;
        }

        $this->logger->debug('[{checker}] run gitleaks scan on repository...', [
            'checker' => $this->getName(),
            'repository' => $project->getFullName(),
        ]);

        try {
            $content = $this->gitleaksRunner->detect(
                $this->localFilesystem->getGitRepositoryPath($project->getFullName())
            );
            $this->reportStore->write(self::TOOL_NAME, $project->getId(), $content);
        } catch (GitleaksException|ReportStoreException $e) {
            $this->logger->error($e->getMessage(), [
                'checker' => $this->getName(),
                'repository' => $project->getFullName(),
            ]);

            return [
                'success' => false,
                'secrets' => false,
                'summary' => false,
            ];
        }

        $report = SarifReport::fromJson($content);
        $secrets = $report->countByRuleId();

        return [
            'success' => true,
            'secrets' => $secrets,
            'summary' => ['count' => $report->count()],
        ];
    }

    /**
     * Test if the scan is enabled and if gitleaks is available.
     *
     * Note that the availability is resolved once and then reused.
     */
    private function isEnabled(): bool
    {
        if (null !== $this->enabled) {
            return $this->enabled;
        }

        if (!$this->gitleaksEnabled) {
            return $this->enabled = false;
        }

        try {
            $version = $this->gitleaksRunner->getVersion();
            $this->logger->info('[{checker}] gitleaks executable found (version={gitleaks_version})', [
                'checker' => $this->getName(),
                'gitleaks_version' => $version,
            ]);

            return $this->enabled = true;
        } catch (GitleaksException) {
            $this->logger->warning('[{checker}] gitleaks not found, scan disabled', [
                'checker' => $this->getName(),
            ]);

            return $this->enabled = false;
        }
    }
}
