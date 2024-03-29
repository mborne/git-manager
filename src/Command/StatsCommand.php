<?php

namespace MBO\GitManager\Command;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Git\Analyzer;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clone remote git projects to local directory.
 *
 * @author mborne
 */
class StatsCommand extends Command
{
    public function __construct(
        private LocalFilesystem $localFilesystem,
        private Analyzer $analyzer
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
        $logger = $this->createLogger($output);

        $repositories = $this->localFilesystem->getRepositories();

        $results = [];
        foreach ($repositories as $repository) {
            $logger->info(sprintf('%s ...', $repository));
            try {
                $gitRepository = new GitRepository(
                    $this->localFilesystem->getRootPath().'/'.$repository
                );
                $results[$repository] = $this->analyzer->getMetadata($gitRepository);
            } catch (\Exception $e) {
                $logger->error(sprintf('%s : %s', $repository, $e->getMessage()));
            }
        }

        $logger->info(sprintf('save stats : %s', $this->localFilesystem->getRootPath().'/repositories.json'));
        $this->localFilesystem->write(
            'repositories.json',
            json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return self::SUCCESS;
    }

    /**
     * Create console logger.
     */
    protected function createLogger(OutputInterface $output): ConsoleLogger
    {
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ];

        return new ConsoleLogger($output, $verbosityLevelMap);
    }
}
