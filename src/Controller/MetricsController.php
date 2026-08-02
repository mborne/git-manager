<?php

namespace MBO\GitManager\Controller;

use MBO\GitManager\Export\MetricsExporter;
use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class MetricsController extends AbstractController
{
    #[Route('/metrics', name: 'app_metrics')]
    public function metrics(ProjectRepository $repository, MetricsExporter $exporter): Response
    {
        $content = $exporter->exportProjects($repository->findAll());

        return new Response($content, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
