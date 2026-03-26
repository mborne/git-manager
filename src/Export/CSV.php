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
            'VISIBILITY',
            'ARCHIVED',
            'README',
            'LICENSE',
            'SIZE_MO',
        ]);

        foreach ($projects as $project) {
            fputcsv($handle, [
                $project->getFullName(),
                $project->getVisibility() ?? 'unknown',
                $project->isArchived() ? '1' : '0',
                ($project->getChecks()['readme'] ?? false) ? '1' : '0',
                $this->licenseCsvValue($project),
                $this->sizeMo($project),
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

    private function licenseCsvValue(Project $project): string
    {
        $license = $project->getChecks()['license'] ?? false;

        return $license ? 'FOUND' : 'MISSING';
    }

    private function sizeMo(Project $project): string
    {
        $sizeBytes = $project->getMetadata()['size'] ?? 0;
        if (!is_numeric($sizeBytes)) {
            return '0.0';
        }

        return number_format(((float) $sizeBytes) / (1024 * 1024), 1, '.', '');
    }
}
