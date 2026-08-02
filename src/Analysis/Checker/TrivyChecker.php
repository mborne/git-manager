<?php

namespace MBO\GitManager\Analysis\Checker;

use MBO\GitManager\Analysis\Checker\Trivy\TrivyException;
use MBO\GitManager\Analysis\Checker\Trivy\TrivyReport;
use MBO\GitManager\Analysis\Checker\Trivy\TrivyRunner;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Storage\GitRepositoryStore;
use MBO\GitManager\Storage\ReportStoreException;
use MBO\GitManager\Storage\ReportStoreInterface;
use Psr\Log\LoggerInterface;

/**
 * Perform a scan with trivy producing a JSON report.
 */
final class TrivyChecker implements CheckerInterface
{
    /**
     * Name of the checker, used as key in the check results.
     */
    public const NAME = 'trivy';

    /**
     * Availability of trivy, resolved on the first check.
     */
    private ?bool $enabled = null;

    public function __construct(
        private bool $trivyEnabled,
        private TrivyRunner $trivyRunner,
        private GitRepositoryStore $gitRepositoryStore,
        private ReportStoreInterface $reportStore,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{success:bool,vulnerabilities:array<string,string>|false,summary:array<string,int>|false}|null
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

        $this->logger->debug('[{checker}] run trivy fs on repository...', [
            'checker' => $this->getName(),
            'repository' => $project->getFullName(),
        ]);

        try {
            $content = $this->trivyRunner->scan(
                $this->gitRepositoryStore->getPath($project->getFullName())
            );
            $this->reportStore->write(self::NAME, $project->getId(), $content);
        } catch (TrivyException|ReportStoreException $e) {
            $this->logger->error($e->getMessage(), [
                'checker' => $this->getName(),
                'repository' => $project->getFullName(),
            ]);

            return [
                'success' => false,
                'vulnerabilities' => false,
                'summary' => false,
            ];
        }

        $report = TrivyReport::fromJson($content);

        return [
            'success' => true,
            'vulnerabilities' => $report->getSeverityById(),
            'summary' => array_merge(
                array_fill_keys(TrivyRunner::SEVERITIES, 0),
                $report->countBySeverity()
            ),
        ];
    }

    /**
     * Test if the scan is enabled and if trivy is available.
     *
     * Note that the availability is resolved once and then reused.
     */
    private function isEnabled(): bool
    {
        if (null !== $this->enabled) {
            return $this->enabled;
        }

        if (!$this->trivyEnabled) {
            return $this->enabled = false;
        }

        try {
            $version = $this->trivyRunner->getVersion();
            $this->logger->info('[{checker}] trivy executable found (version={trivy_version})', [
                'checker' => $this->getName(),
                'trivy_version' => $version,
            ]);

            return $this->enabled = true;
        } catch (TrivyException) {
            $this->logger->warning('[{checker}] trivy not found, scan disabled', [
                'checker' => $this->getName(),
            ]);

            return $this->enabled = false;
        }
    }
}
