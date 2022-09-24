<?php

namespace MBO\GitManager\Command;

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
use MBO\GitManager\Filesystem\LocalFilesystem;


/**
 * Clone remote git projects to local directory
 *
 * @author mborne
 */
class FetchAllCommand extends Command {

    /**
     * @var LocalFilesystem
     */
    private $localFilesystem ;

    public function __construct(LocalFilesystem $localFilesystem)
    {
        parent::__construct();

        $this->localFilesystem = $localFilesystem;
    }

    /**
     * {@inheritDoc}
     */
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

            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Remote git type (gitlab-v4,github,gogs-v1,...)')

            ->addOption('orgs', 'o', InputOption::VALUE_REQUIRED, 'Find projects according to given organization names')
            ->addOption('users', 'u', InputOption::VALUE_REQUIRED, 'Find projects according to given user names')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $logger = $this->createLogger($output);

        $logger->info('[git:fetch-all] started...');

        $dataDir = $this->localFilesystem->getRootPath();
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

        $type = $input->getOption('type');
        if ( ! empty($type) ){
            $clientOptions->setType($type);
        }
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

        foreach ( $projects as $project ){
            $logger->info(sprintf(
                '[%s] %s ...',
                $project->getName(),
                $project->getHttpUrl()
            ));

            $host = parse_url($project->getHttpUrl(), PHP_URL_HOST);
            $localPath = $dataDir.'/'.$host.'/'.$project->getName();
            if ( file_exists($localPath) ){
                $command = sprintf(
                    'cd %s && git fetch origin -p && git pull',
                    escapeshellarg($localPath),
                    escapeshellarg($project->getHttpUrl()),
                    escapeshellarg($localPath)
                );
            }else{
                $command = sprintf(
                    'git clone %s %s',
                    escapeshellarg($project->getHttpUrl()),
                    escapeshellarg($localPath)
                );
            }
            system($command);
        }

        $logger->info('[git:fetch-all] completed');

        return self::SUCCESS;
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
