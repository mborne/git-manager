<?php

namespace MBO\GitManager\Export;

use MBO\GitManager\Entity\Project;

class CSV
{
    /**
     * @param iterable<Project> $projects
     */
    public function exportProjects(iterable $projects): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open temporary stream for CSV export.');
        }

        fputcsv($handle, [
            'NAME',
            'DESCRIPTION',
            'VISIBILITY',
            'ARCHIVED',
            'README',
            'LICENSE',
            'SIZE_MO',
            'LAST_ACTIVITY',
        ]);

        foreach ($projects as $project) {
            fputcsv($handle, [
                $project->getFullName(),
                $project->getDescription() ?? '',
                $project->getVisibility() ?? 'unknown',
                $project->isArchived() ? '1' : '0',
                $this->readmeCsvValue($project),
                $this->licenseCsvValue($project),
                $this->sizeMo($project),
                $this->lastActivityCsvValue($project),
            ]);
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

        return number_format(((float) $sizeBytes) / (1024 * 1024), 1, '.', '');
    }

    private function lastActivityCsvValue(Project $project): string
    {
        $lastActivity = $project->getMetadata()['activity'] ?? [];
        if (empty($lastActivity)) {
            return '0000-00-00';
        }

        return max(array_keys($lastActivity));
    }
}
