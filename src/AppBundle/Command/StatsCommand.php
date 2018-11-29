<?php

namespace AppBundle\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;

use MBO\SatisGitlab\Satis\ConfigBuilder;
use GuzzleHttp\Client as GuzzleHttpClient;

use MBO\RemoteGit\ClientFactory;
use MBO\RemoteGit\ClientInterface;
use MBO\RemoteGit\FindOptions;
use MBO\RemoteGit\ProjectInterface;
use MBO\RemoteGit\ClientOptions;
use MBO\RemoteGit\Filter\FilterCollection;
use AppBundle\Filesystem\LocalFilesystem;

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

    public function __construct(LocalFilesystem $localFilesystem)
    {
        parent::__construct();

        $this->localFilesystem = $localFilesystem;
    }


    protected function configure() {
        $this
            ->setName('git:stats')
            ->setDescription('Compute stats on local repositories')

            ->addOption('output', 'O', InputOption::VALUE_REQUIRED, 'Output file', 'php://output')
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
            $gitRepository = new GitRepository(
                $this->localFilesystem->getRootPath().'/'.$repository
            );
            $metadata = array(
                'name' => $repository,
                'size' => $gitRepository->getSize()
            );
            $logger->info(json_encode($metadata));
            $results[] = $metadata;
        }

        file_put_contents(
            $input->getOption('output'),
            json_encode($results,JSON_PRETTY_PRINT)
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
