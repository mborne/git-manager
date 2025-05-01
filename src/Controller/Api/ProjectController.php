<?php

namespace MBO\GitManager\Controller\Api;

use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProjectController extends AbstractController
{
    #[Route('/api/projects', name: 'api_project_list')]
    public function list(
        ProjectRepository $repository,
    ): Response {
        $repositories = $repository->findAll();

        return $this->json($repositories);
    }
}
