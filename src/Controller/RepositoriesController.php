<?php

namespace MBO\GitManager\Controller;

use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RepositoriesController extends AbstractController
{
    #[Route('/api/repositories', name: 'app_repositories_list')]
    public function list(
        ProjectRepository $repository,
    ): Response {
        $repositories = $repository->findAll();

        return $this->json($repositories);
    }
}
