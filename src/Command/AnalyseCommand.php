<?php

namespace MBO\GitManager\Command;

use Doctrine\ORM\EntityManagerInterface;
use MBO\GitManager\Analysis\Analyzer;
use MBO\GitManager\Repository\ProjectRepository;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Analyse local git projects.
 *
 * @author mborne
 */
final class AnalyseCommand extends Command
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $em,
        private Analyzer $analyzer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('git:analyse')
            ->setDescription('Run analysis on all fetched projects')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit the number of projects to analyse')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Filter projects by full name prefix (e.g. github.com/IGNF)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = $this->createLogger($output);

        $logger->info('[git:analyse] started...');

        $limit = $input->getOption('limit');
        $prefix = $input->getOption('prefix');

        if (null !== $prefix) {
            $projects = $this->projectRepository->findByPrefix($prefix);
        } else {
            $projects = $this->projectRepository->findAll();
        }

        $count = 0;
        foreach ($projects as $project) {
            if (null !== $limit && $count >= (int) $limit) {
                break;
            }
            $logger->info(sprintf(
                '[%s] analysing...',
                $project->getFullName()
            ));
            try {
                $this->analyzer->analyze($project);
                $this->em->persist($project);
                $this->em->flush();
                ++$count;
            } catch (\Exception $e) {
                $logger->error(sprintf(
                    '[%s] analysis failed : "%s"',
                    $project->getFullName(),
                    $e->getMessage()
                ));
            }
        }

        $logger->info('[git:analyse] completed');

        return self::SUCCESS;
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
