<?php

namespace MBO\GitManager\Controller\Api;

use MBO\GitManager\Export\CSV;
use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class ProjectController extends AbstractController
{

    #[Route('/api/projects', name: 'api_project_list')]
    public function list(
        ProjectRepository $repository,
    ): Response {
        $projects = $repository->findAll();

        return $this->json($projects);
    }

    #[Route('/api/projects.csv', name: 'api_project_list_csv', priority: 10)]
    public function listCsv(ProjectRepository $repository, CSV $csv): Response
    {
        $content = $csv->exportProjects($repository->findAll());

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="projects.csv"');

        return $response;
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
}
