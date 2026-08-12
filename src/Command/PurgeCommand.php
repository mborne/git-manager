<?php

namespace MBO\GitManager\Command;

use Doctrine\ORM\EntityManagerInterface;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Repository\ProjectRepository;
use MBO\GitManager\Storage\GitRepositoryStore;
use MBO\GitManager\Storage\ReportStoreInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Remove a project from the database and the data directory, typically when the
 * corresponding repository has been deleted on the remote git hosting service.
 *
 * @author mborne
 */
final class PurgeCommand extends Command
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $em,
        private GitRepositoryStore $gitRepositoryStore,
        private ReportStoreInterface $reportStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('git:purge')
            ->setDescription('Remove a repository from the database and the filesystem')
            ->addArgument(
                'projectId',
                InputArgument::REQUIRED,
                'The id of the project, as displayed in the URL of the project'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = $this->createLogger($output);

        $projectId = $input->getArgument('projectId');

        $logger->info(sprintf('[git:purge] %s ...', $projectId));

        if (!Uuid::isValid($projectId)) {
            $logger->error(sprintf('[%s] is not a valid project id', $projectId));

            return self::FAILURE;
        }

        $project = $this->projectRepository->find(Uuid::fromString($projectId));
        if (null === $project) {
            $logger->error(sprintf('[%s] project not found in the database', $projectId));

            return self::FAILURE;
        }

        try {
            $this->purgeReports($project, $logger);
            $this->purgeGitRepository($project, $logger);
            $this->purgeProject($project, $logger);
        } catch (\Exception $e) {
            $logger->error(sprintf('[%s] purge failed : "%s"', $projectId, $e->getMessage()));

            return self::FAILURE;
        }

        $logger->info('[git:purge] completed');

        return self::SUCCESS;
    }

    /**
     * Remove the reports produced by the analysis tools (gitleaks, trivy,...).
     */
    private function purgeReports(Project $project, LoggerInterface $logger): void
    {
        $logger->info(sprintf('[%s] purging reports ...', $project->getFullName()));
        $this->reportStore->remove($project->getId());
        $logger->info(sprintf('[%s] reports removed', $project->getFullName()));
    }

    /**
     * Remove the git repository cloned in the data directory, which may already
     * be missing.
     */
    private function purgeGitRepository(Project $project, LoggerInterface $logger): void
    {
        $logger->info(sprintf('[%s] purging git repository ...', $project->getFullName()));
        $fullName = $project->getFullName();
        if (!$this->gitRepositoryStore->exists($fullName)) {
            $logger->warning(sprintf('[%s] no git repository in the data directory', $fullName));

            return;
        }

        $this->gitRepositoryStore->remove($fullName);
        $logger->info(sprintf(
            '[%s] git repository removed (%s)',
            $fullName,
            $this->gitRepositoryStore->getPath($fullName)
        ));
    }

    /**
     * Remove the database entry of a project.
     */
    private function purgeProject(Project $project, LoggerInterface $logger): void
    {
        $logger->info(sprintf('[%s] purging project from the database ...', $project->getFullName()));
        $this->em->remove($project);
        $this->em->flush();
        $logger->info(sprintf('[%s] project removed from the database', $project->getFullName()));
    }

    /**
     * Create console logger.
     */
    private function createLogger(OutputInterface $output): ConsoleLogger
    {
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ];

        return new ConsoleLogger($output, $verbosityLevelMap);
    }
}
