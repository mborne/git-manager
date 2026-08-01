<?php

namespace MBO\GitManager\Controller;

use MBO\GitManager\Analysis\Checker\Gitleaks\SarifReport;
use MBO\GitManager\Analysis\Checker\SecretChecker;
use MBO\GitManager\Filesystem\FileReaderInterface;
use MBO\GitManager\Filesystem\LocalFilesystemInterface;
use MBO\GitManager\Repository\ProjectRepository;
use MBO\GitManager\Storage\ReportStoreInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

final class ProjectController extends AbstractController
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
        LocalFilesystemInterface $localFilesystem,
        FileReaderInterface $fileReader,
        ReportStoreInterface $reportStore,
    ): Response {
        $project = $repository->find($id);
        if (is_null($project)) {
            throw $this->createNotFoundException('project not found');
        }

        $trivyReportPathTxt = $localFilesystem->getTrivyReportPath($project).'.txt';
        $trivyReportTxt = $fileReader->read($trivyReportPathTxt) ?? 'NO-DATA';

        $secretReport = SarifReport::fromJson(
            $reportStore->read(SecretChecker::TOOL_NAME, $project->getId())
        );
        $secretFindings = $secretReport->getFindings(
            $localFilesystem->getGitRepositoryPath($project->getFullName()).DIRECTORY_SEPARATOR
        );

        return $this->render('project/details.html.twig', [
            'project' => $project,
            'trivyReportTxt' => $trivyReportTxt,
            'secretFindings' => $secretFindings,
        ]);
    }
}
