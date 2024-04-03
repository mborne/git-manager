<?php

namespace MBO\GitManager\Controller\Api;

use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProjectsController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository
    ) {
    }

    #[Route('/api/projects', name: 'app_projects_list')]
    public function list(): Response
    {
        /** @var Project[] $projects */
        $projects = $this->projectRepository->findAll();

        return $this->json($projects);
    }

    #[Route('/api/projects/{id}', name: 'app_projects_get')]
    public function get(string $id): Response
    {
        /** @var Project $project */
        $project = $this->projectRepository->find($id);

        return $this->json($project);
    }

    #[Route('/api/projects/{id}/trivy', name: 'app_projects_get_trivy')]
    public function trivy(string $id, LocalFilesystem $localFilesystem): Response
    {
        /** @var Project $project */
        $project = $this->projectRepository->find($id);
        $trivyContent = $localFilesystem->read($project->getName().'/.trivy.json');
        return new Response($trivyContent,200,[
            'Content-Type' => 'application/json'
        ]);
    }
}
