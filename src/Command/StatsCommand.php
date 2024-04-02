<?php

namespace MBO\GitManager\Command;

use Doctrine\ORM\EntityManagerInterface;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Git\Analyzer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clone remote git projects to local directory.
 *
 * @author mborne
 */
class StatsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LocalFilesystem $localFilesystem,
        private Analyzer $analyzer,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('git:stats')
            ->setDescription('Compute stats on local repositories')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRepository = $this->entityManager->getRepository(Project::class);

        $this->logger->info(sprintf('[git:stats] list repositories ...'));
        $repositories = $this->localFilesystem->getRepositories();
        foreach ($repositories as $repository) {
            $this->logger->info(sprintf('[git:stats] process %s ...', $repository));

            /** @var Project $project */
            $project = $projectRepository->findOneBy([
                'name' => $repository,
            ]);
            if (null == $project) {
                $project = new Project();
                $project->setName($repository);
            }

            try {
                $this->analyzer->update($project);
                $this->entityManager->persist($project);
            } catch (\Exception $e) {
                $this->logger->error(sprintf('[git:stats] %s : %s', $repository, $e->getMessage()));
            }
        }

        $this->logger->info(sprintf('[git:stats] save stats ...'));
        $this->entityManager->flush();

        $this->logger->info(sprintf('[git:stats] completed.'));

        return self::SUCCESS;
    }
}
