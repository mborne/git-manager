<?php

namespace MBO\GitManager\Controller;

use MBO\GitManager\Analysis\Checker\Gitleaks\SarifReport;
use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Analysis\Checker\Trivy\TrivyReport;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Repository\ProjectRepository;
use MBO\GitManager\Storage\GitRepositoryStore;
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
        GitRepositoryStore $gitRepositoryStore,
        ReportStoreInterface $reportStore,
    ): Response {
        $project = $repository->find($id);
        if (is_null($project)) {
            throw $this->createNotFoundException('project not found');
        }

        $stripPrefix = $gitRepositoryStore->getPath(
            $project->getFullName()
        ).DIRECTORY_SEPARATOR;

        $secretReport = SarifReport::fromJson(
            $reportStore->read(GitleaksChecker::NAME, $project->getId())
        );

        $vulnReport = TrivyReport::fromJson(
            $reportStore->read(TrivyChecker::NAME, $project->getId())
        );
        $vulnerabilities = $vulnReport->getVulnerabilities($stripPrefix);

        return $this->render('project/details.html.twig', [
            'project' => $project,
            'secretCount' => $secretReport->count(),
            'secretCountByRuleId' => $secretReport->countByRuleId(),
            'vulnerabilities' => $vulnerabilities,
        ]);
    }

    /**
     * Display the secrets detected by gitleaks.
     */
    #[Route('/{id}/secrets', name: 'app_project_secrets')]
    public function secrets(
        ProjectRepository $repository,
        Uuid $id,
        GitRepositoryStore $gitRepositoryStore,
        ReportStoreInterface $reportStore,
    ): Response {
        $project = $repository->find($id);
        if (is_null($project)) {
            throw $this->createNotFoundException('project not found');
        }

        $stripPrefix = $gitRepositoryStore->getPath(
            $project->getFullName()
        ).DIRECTORY_SEPARATOR;

        $secretReport = SarifReport::fromJson(
            $reportStore->read(GitleaksChecker::NAME, $project->getId())
        );

        return $this->render('project/secrets.html.twig', [
            'project' => $project,
            'secretFindings' => $secretReport->getFindings($stripPrefix),
            'secretCountByRuleId' => $secretReport->countByRuleId(),
        ]);
    }
}
