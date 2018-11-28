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


/**
 * Clone remote git projects to local directory
 *
 * @author mborne
 */
class FetchAllCommand extends Command {

    protected function configure() {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('git:fetch-all')

            // the short description shown while running "php bin/console list"
            ->setDescription('Fetch all repositories to local directory')
            /* 
             * Git client options 
             */
            ->addArgument('url', InputArgument::REQUIRED)
            ->addArgument('token')

            ->addOption('orgs', 'o', InputOption::VALUE_REQUIRED, 'Find projects according to given organization names')
            ->addOption('users', 'u', InputOption::VALUE_REQUIRED, 'Find projects according to given user names')

            ->addOption('data',null,InputOption::VALUE_REQUIRED, "Data directory", getcwd().'/data' )
        ;
    }

    /**
     * @{inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $logger = $this->createLogger($output);

        $dataDir = $input->getOption('data');
        if ( ! file_exists($dataDir) ){
            throw new \Exception("$dataDir not found");
        }
        if ( ! is_dir($dataDir) ){
            throw new \Exception("$dataDir is not a directory");
        }

        /*
         * Create git client according to parameters
         */
        $clientOptions = new ClientOptions();
        $clientOptions->setUrl($input->getArgument('url'));
        $clientOptions->setToken($input->getArgument('token'));
        $client = ClientFactory::createClient(
            $clientOptions,
            $logger
        );

        /*
         * Create repository listing filter (git level)
         */
        $findOptions = new FindOptions();
        /* orgs option */
        $orgs = $input->getOption('orgs');
        if ( ! empty($orgs) ){
            $findOptions->setOrganizations(explode(',',$orgs));
        }
        /* users option */
        $users = $input->getOption('users');
        if ( ! empty($users) ){
            $findOptions->setUsers(explode(',',$users));
        }

        /*
         * Find projects
         */
        $projects = $client->find($findOptions);


        $gitWrapper = new GitWrapper();
        foreach ( $projects as $project ){
            $logger->info(sprintf(
                '[%s] %s ...',
                $project->getName(),
                $project->getHttpUrl()
            ));
            $localPath = $dataDir.'/'.$project->getName();
            // TODO switch with fetch if already exists
            $command = sprintf(
                'git clone %s %s',
                escapeshellarg($project->getHttpUrl()),
                escapeshellarg($localPath)
            );
            system($command);
        }
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
