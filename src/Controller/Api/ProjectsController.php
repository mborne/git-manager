<?php

namespace MBO\GitManager\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use MBO\GitManager\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProjectsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/api/projects', name: 'app_projects_list')]
    public function list(): Response
    {
        $projectRepository = $this->entityManager->getRepository(Project::class);
        /** @var Project[] $projects */
        $projects = $projectRepository->findAll();

        return $this->json($projects);
    }

    #[Route('/api/projects/{id}', name: 'app_projects_get')]
    public function get(string $id): Response
    {
        $projectRepository = $this->entityManager->getRepository(Project::class);
        /** @var Project $project */
        $project = $projectRepository->find($id);

        return $this->json($project);
    }
}
