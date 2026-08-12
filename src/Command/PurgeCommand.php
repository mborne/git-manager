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
                'fullName',
                InputArgument::REQUIRED,
                'The full name of the repository (ex : github.com/mborne/git-manager)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = $this->createLogger($output);

        $fullName = $input->getArgument('fullName');

        $logger->info(sprintf('[git:purge] %s ...', $fullName));

        /** @var Project|null */
        $project = $this->projectRepository->findOneBy(['fullName' => $fullName]);
        $repositoryExists = $this->gitRepositoryStore->exists($fullName);

        if (null === $project && !$repositoryExists) {
            $logger->error(sprintf(
                '[%s] neither found in the database nor in the data directory',
                $fullName
            ));

            return self::FAILURE;
        }

        try {
            if (null !== $project) {
                $this->purgeProject($project, $logger);
            } else {
                $logger->warning(sprintf('[%s] project not found in the database', $fullName));
            }

            if ($repositoryExists) {
                $this->purgeGitRepository($fullName, $logger);
            } else {
                $logger->warning(sprintf('[%s] no git repository in the data directory', $fullName));
            }
        } catch (\Exception $e) {
            $logger->error(sprintf('[%s] purge failed : "%s"', $fullName, $e->getMessage()));

            return self::FAILURE;
        }

        $logger->info('[git:purge] completed');

        return self::SUCCESS;
    }

    /**
     * Remove the reports and the database entry of a project.
     */
    private function purgeProject(Project $project, LoggerInterface $logger): void
    {
        $fullName = $project->getFullName();

        /* the reports are removed first as they are located by the project id */
        $this->reportStore->remove($project->getId());
        $logger->info(sprintf('[%s] reports removed', $fullName));

        $this->em->remove($project);
        $this->em->flush();
        $logger->info(sprintf('[%s] project removed from the database', $fullName));
    }

    /**
     * Remove the git repository cloned in the data directory.
     */
    private function purgeGitRepository(string $fullName, LoggerInterface $logger): void
    {
        $this->gitRepositoryStore->remove($fullName);
        $logger->info(sprintf(
            '[%s] git repository removed (%s)',
            $fullName,
            $this->gitRepositoryStore->getPath($fullName)
        ));
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
