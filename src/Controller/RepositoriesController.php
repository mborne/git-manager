<?php

namespace MBO\GitManager\Controller;

use MBO\GitManager\Filesystem\LocalFilesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RepositoriesController extends AbstractController
{
    #[Route('/api/repositories', name: 'app_repositories_list')]
    public function list(LocalFilesystem $localFilesystem): Response
    {
        $path = $localFilesystem->getRootPath().'/repositories.json';
        if (!file_exists($path)) {
            return $this->json('repositories.json is not available (run git:stats)', 404);
        }
        $repositories = json_decode(file_get_contents($path));
        return $this->json($repositories);
    }
}
