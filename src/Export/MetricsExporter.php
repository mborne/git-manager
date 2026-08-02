<?php

namespace MBO\GitManager\Export;

use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Analysis\Checker\Trivy\TrivyRunner;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Entity\Project;

/**
 * Renders projects as prometheus metrics (text exposition format).
 */
final class MetricsExporter
{
    /**
     * @param iterable<Project> $projects
     */
    public function exportProjects(iterable $projects): string
    {
        // materialized as the projects are traversed once per metric.
        $projects = is_array($projects) ? $projects : iterator_to_array($projects);

        $lines = [];

        $lines[] = '# HELP gitmanager_trivy_total Total number of vulnerabilities for a project';
        $lines[] = '# TYPE gitmanager_trivy_total gauge';
        foreach ($projects as $project) {
            $checks = $project->getChecks();
            $total = $this->getVulnerabilityTotal($checks);
            $lines[] = sprintf('gitmanager_trivy_total{%s} %d', $this->getProjectLabels($project), $total);
        }

        foreach (TrivyRunner::SEVERITIES as $severity) {
            $metricName = sprintf('gitmanager_trivy_%s', strtolower($severity->value));
            $lines[] = sprintf('# HELP %s Number of %s vulnerabilities for a project', $metricName, strtolower($severity->value));
            $lines[] = sprintf('# TYPE %s gauge', $metricName);
            foreach ($projects as $project) {
                $checks = $project->getChecks();
                $count = $checks[TrivyChecker::NAME]['summary'][$severity->value] ?? 0;
                $lines[] = sprintf('%s{%s} %d', $metricName, $this->getProjectLabels($project), $count);
            }
        }

        $lines[] = '# HELP gitmanager_gitleaks_total Total number of secrets detected for a project';
        $lines[] = '# TYPE gitmanager_gitleaks_total gauge';
        foreach ($projects as $project) {
            $checks = $project->getChecks();
            $secretCount = $checks[GitleaksChecker::NAME]['summary']['count'] ?? 0;
            $lines[] = sprintf('gitmanager_gitleaks_total{%s} %d', $this->getProjectLabels($project), $secretCount);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param array<string,mixed> $checks
     */
    private function getVulnerabilityTotal(array $checks): int
    {
        $summary = $checks[TrivyChecker::NAME]['summary'] ?? [];
        if (!is_array($summary)) {
            return 0;
        }
        $total = 0;
        foreach ($summary as $count) {
            $total += (int) $count;
        }

        return $total;
    }

    private function getProjectLabels(Project $project): string
    {
        return sprintf(
            'project="%s",archived="%s",visibility="%s"',
            $this->escapeLabel($project->getFullName()),
            $project->isArchived() ? 'true' : 'false',
            $this->escapeLabel($project->getVisibility() ?? 'unknown')
        );
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
