<?php

namespace MBO\GitManager\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MBO\GitManager\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RepositoriesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/api/repositories', name: 'app_repositories_list')]
    public function list(): Response
    {
        $projectRepository = $this->entityManager->getRepository(Project::class);
        /** @var Project[] $projects */
        $projects = $projectRepository->findAll();

        // TODO : flatten rendering
        $repositories = [];
        foreach ($projects as $project) {
            $repositories[$project->getName()] = $project->getMetadata();
        }

        return $this->json($repositories);
    }
}
