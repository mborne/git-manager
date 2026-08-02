<?php

namespace MBO\GitManager\Controller;

use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class MetricsController extends AbstractController
{
    #[Route('/metrics', name: 'app_metrics')]
    public function metrics(ProjectRepository $repository): Response
    {
        $projects = $repository->findAll();

        $lines = [];

        $lines[] = '# HELP gitmanager_vulnerabilities_total Total number of vulnerabilities for a project';
        $lines[] = '# TYPE gitmanager_vulnerabilities_total gauge';
        foreach ($projects as $project) {
            $checks = $project->getChecks();
            $total = $this->getVulnerabilityTotal($checks);
            $lines[] = sprintf('gitmanager_vulnerabilities_total{%s} %d', $this->getProjectLabels($project), $total);
        }

        $lines[] = '# HELP gitmanager_vulnerabilities_critical Number of critical vulnerabilities for a project';
        $lines[] = '# TYPE gitmanager_vulnerabilities_critical gauge';
        foreach ($projects as $project) {
            $checks = $project->getChecks();
            $critical = $checks[TrivyChecker::NAME]['summary']['CRITICAL'] ?? 0;
            $lines[] = sprintf('gitmanager_vulnerabilities_critical{%s} %d', $this->getProjectLabels($project), $critical);
        }

        $lines[] = '# HELP gitmanager_vulnerabilities_high Number of high vulnerabilities for a project';
        $lines[] = '# TYPE gitmanager_vulnerabilities_high gauge';
        foreach ($projects as $project) {
            $checks = $project->getChecks();
            $high = $checks[TrivyChecker::NAME]['summary']['HIGH'] ?? 0;
            $lines[] = sprintf('gitmanager_vulnerabilities_high{%s} %d', $this->getProjectLabels($project), $high);
        }

        $lines[] = '# HELP gitmanager_secrets_total Total number of secrets detected for a project';
        $lines[] = '# TYPE gitmanager_secrets_total gauge';
        foreach ($projects as $project) {
            $checks = $project->getChecks();
            $secretCount = $checks[GitleaksChecker::NAME]['summary']['count'] ?? 0;
            $lines[] = sprintf('gitmanager_secrets_total{%s} %d', $this->getProjectLabels($project), $secretCount);
        }

        $content = implode("\n", $lines)."\n";

        return new Response($content, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
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
