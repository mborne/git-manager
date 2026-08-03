<?php

namespace MBO\GitManager\Export;

use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Analysis\Checker\Trivy\Severity;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Entity\Project;

final class CsvExporter
{
    /**
     * The severities exported as a dedicated column, from the most to the least
     * critical one.
     */
    private const EXPORTED_SEVERITIES = [Severity::CRITICAL, Severity::HIGH, Severity::LOW];

    /**
     * @param iterable<Project> $projects
     */
    public function exportProjects(iterable $projects): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open temporary stream for CSV export.');
        }

        $header = [
            'NAME',
            'DESCRIPTION',
            'VISIBILITY',
            'ARCHIVED',
            'README',
            'LICENSE',
            'SIZE_MO',
            'LAST_ACTIVITY',
        ];
        foreach (self::EXPORTED_SEVERITIES as $severity) {
            $header[] = 'TRIVY_'.$severity->value;
        }
        $header[] = 'GITLEAKS_COUNT';
        fputcsv($handle, $header);

        foreach ($projects as $project) {
            $row = [
                $project->getFullName(),
                $project->getDescription() ?? '',
                $project->getVisibility() ?? 'unknown',
                $project->isArchived() ? '1' : '0',
                $this->readmeCsvValue($project),
                $this->licenseCsvValue($project),
                $this->sizeMo($project),
                $this->lastActivityCsvValue($project),
            ];
            foreach (self::EXPORTED_SEVERITIES as $severity) {
                $row[] = $this->trivyCsvValue($project, $severity);
            }
            $row[] = $this->gitleaksCsvValue($project);
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        if (!\is_string($content)) {
            throw new \RuntimeException('Unable to read CSV stream.');
        }

        return $content;
    }

    private function readmeCsvValue(Project $project): string
    {
        $readme = $project->getChecks()['readme'] ?? false;

        return $readme ? '1' : '0';
    }

    private function licenseCsvValue(Project $project): string
    {
        $license = $project->getChecks()['license'] ?? false;

        return $license ? $license : '0';
    }

    private function sizeMo(Project $project): string
    {
        $sizeBytes = $project->getMetadata()['size'] ?? 0;
        if (!is_numeric($sizeBytes)) {
            return '0.0';
        }

        return number_format(((float) $sizeBytes) / (1024.0 * 1024.0), 1, '.', '');
    }

    /**
     * Get the number of vulnerabilities reported by trivy for a severity.
     *
     * Note that the counts are typed as mixed as they are read back from a JSON column.
     */
    private function trivyCsvValue(Project $project, Severity $severity): string
    {
        $count = $project->getChecks()[TrivyChecker::NAME]['summary'][$severity->value] ?? 0;

        return $this->countCsvValue($count);
    }

    /**
     * Get the number of secrets reported by gitleaks.
     */
    private function gitleaksCsvValue(Project $project): string
    {
        $count = $project->getChecks()[GitleaksChecker::NAME]['summary']['count'] ?? 0;

        return $this->countCsvValue($count);
    }

    private function countCsvValue(mixed $count): string
    {
        if (!is_numeric($count)) {
            return '0';
        }

        return (string) (int) $count;
    }

    private function lastActivityCsvValue(Project $project): string
    {
        $lastActivity = $project->getMetadata()['last_activity'] ?? null;
        if (!\is_string($lastActivity)) {
            return '0000-00-00';
        }

        // the metadata is an RFC3339 date, only the day is exported
        return substr($lastActivity, 0, 10);
    }
}
