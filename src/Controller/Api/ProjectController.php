<?php

namespace MBO\GitManager\Controller\Api;

use MBO\GitManager\Entity\Project;
use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class ProjectController extends AbstractController
{
    #[Route('/api/projects.csv', name: 'api_project_list_csv', priority: 10)]
    public function listCsv(ProjectRepository $repository): Response
    {
        $projects = $repository->findAll();

        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open temporary stream for CSV export.');
        }

        fputcsv($handle, [
            'NAME',
            'ARCHIVED',
            'VISIBILITY',
            'README',
            'LICENSE',
            'LAST_ACTIVITY',
            'SIZE_MO',
        ]);

        foreach ($projects as $project) {
            fputcsv($handle, [
                $project->getFullName(),
                $project->isArchived() ? 'YES' : 'NO',
                $project->getVisibility() ?? 'unknown',
                ($project->getChecks()['readme'] ?? false) ? 'FOUND' : 'MISSING',
                $this->licenseCsvValue($project),
                $this->lastActivityYmd($project),
                $this->sizeMo($project),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="projects.csv"');

        return $response;
    }

    #[Route('/api/projects', name: 'api_project_list')]
    public function list(
        ProjectRepository $repository,
    ): Response {
        $projects = $repository->findAll();

        return $this->json($projects);
    }

    #[Route('/api/projects/{id}', name: 'api_project_get')]
    public function get(
        ProjectRepository $repository,
        Uuid $id,
    ): Response {
        $project = $repository->find($id);
        if (is_null($project)) {
            return $this->json([
                'message' => 'Not found',
            ], 404);
        }

        return $this->json($project);
    }

    private function licenseCsvValue(Project $project): string
    {
        $license = $project->getChecks()['license'] ?? false;

        return $license ? 'FOUND' : 'MISSING';
    }

    /**
     * Last commit day from metadata activity (same ordering as public/js/git-manager.js getLastActivity).
     */
    private function lastActivityYmd(Project $project): string
    {
        $activity = $project->getMetadata()['activity'] ?? null;
        if (!\is_array($activity) || [] === $activity) {
            return '0000-00-00';
        }
        $dates = array_keys($activity);
        sort($dates, SORT_STRING);
        $last = $dates[\array_key_last($dates)];
        if (!\is_string($last) || 8 !== strlen($last)) {
            return '0000-00-00';
        }

        return substr($last, 0, 4).'-'.substr($last, 4, 2).'-'.substr($last, 6, 2);
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
