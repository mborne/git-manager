<?php

namespace MBO\GitManager\Controller;

use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class ProjectController extends AbstractController
{
    #[Route('/', name: 'app_project_index')]
    public function list(): Response
    {
        return $this->render('project/index.html.twig');
    }

    #[Route('/{id}', name: 'app_project_details')]
    public function details(
        ProjectRepository $repository,
        Uuid $id,
        LocalFilesystem $localFilesystem,
    ): Response {
        $project = $repository->find($id);
        if (is_null($project)) {
            throw $this->createNotFoundException('project not found');
        }

        $trivyReportPathTxt = $localFilesystem->getTrivyReportPath($project).'.txt';
        $trivyReportTxt = file_exists($trivyReportPathTxt) ? file_get_contents($trivyReportPathTxt) : 'NO-DATA';

        return $this->render('project/details.html.twig', [
            'project' => $project,
            'trivyReportTxt' => $trivyReportTxt,
        ]);
    }
}
