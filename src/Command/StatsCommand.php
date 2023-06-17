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
    /**
     * @var LocalFilesystem
     */
    private $localFilesystem;

    /**
     * @var Analyzer
     */
    private $analyzer;

    public function __construct(
        LocalFilesystem $localFilesystem,
        Analyzer $analyzer
    ) {
        parent::__construct();

        $this->localFilesystem = $localFilesystem;
        $this->analyzer = $analyzer;
    }

    protected function configure(): void
    {
        $this
            ->setName('git:stats')
            ->setDescription('Compute stats on local repositories')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->createLogger($output);

        $repositories = $this->localFilesystem->getRepositories();

        $results = [];
        foreach ($repositories as $repository) {
            $logger->info(sprintf('%s ...', $repository));
            $gitRepository = new GitRepository(
                $this->localFilesystem->getRootPath().'/'.$repository
            );
            $results[$repository] = $this->analyzer->getMetadata($gitRepository);
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
     *
     * @return ConsoleLogger
     */
    protected function createLogger(OutputInterface $output)
    {
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ];

        return new ConsoleLogger($output, $verbosityLevelMap);
    }
}
