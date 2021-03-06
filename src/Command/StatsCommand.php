<?php

namespace MBO\GitManager\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

use MBO\GitManager\Git\Analyzer;
use MBO\GitManager\Filesystem\LocalFilesystem;
use Gitonomy\Git\Repository as GitRepository;

/**
 * Clone remote git projects to local directory
 *
 * @author mborne
 */
class StatsCommand extends Command {

    /**
     * @var LocalFilesystem
     */
    private $localFilesystem ;

    /**
     * @var Analyzer
     */
    private $analyzer;

    public function __construct(
        LocalFilesystem $localFilesystem,
        Analyzer $analyzer
    )
    {
        parent::__construct();

        $this->localFilesystem = $localFilesystem;
        $this->analyzer = $analyzer;
    }


    protected function configure() {
        $this
            ->setName('git:stats')
            ->setDescription('Compute stats on local repositories')
        ;
    }

    /**
     * @{inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $logger = $this->createLogger($output);

        $repositories = $this->localFilesystem->getRepositories();

        $results = array();
        foreach ( $repositories as $repository ){
            $logger->info(sprintf("%s ...",$repository));
            $gitRepository = new GitRepository(
                $this->localFilesystem->getRootPath().'/'.$repository
            );
            $results[$repository] = $this->analyzer->getMetadata($gitRepository);
        }

        $logger->info(sprintf("save stats : %s", $this->localFilesystem->getRootPath().'/repositories.json' ));
        $this->localFilesystem->put(
            'repositories.json',
            json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Create console logger
     * @param OutputInterface $output
     * @return ConsoleLogger
     */
    protected function createLogger(OutputInterface $output){
        $verbosityLevelMap = array(
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO   => OutputInterface::VERBOSITY_NORMAL,
        );
        return new ConsoleLogger($output,$verbosityLevelMap);
    }

}
