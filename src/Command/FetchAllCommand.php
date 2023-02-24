<?php

namespace MBO\GitManager\Command;

use Exception;
use Gitonomy\Git\Admin as GitAdmin;
use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\RemoteGit\ClientFactory;
use MBO\RemoteGit\ClientOptions;
use MBO\RemoteGit\FindOptions;
use MBO\RemoteGit\ProjectInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clone remote git projects to local directory.
 *
 * @author mborne
 */
class FetchAllCommand extends Command
{
    /**
     * @var LocalFilesystem
     */
    private $localFilesystem;

    public function __construct(LocalFilesystem $localFilesystem)
    {
        parent::__construct();

        $this->localFilesystem = $localFilesystem;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
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
            ->addOption('users', 'u', InputOption::VALUE_REQUIRED, 'Find projects according to given user names');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->createLogger($output);

        $logger->info('[git:fetch-all] started...');

        $dataDir = $this->localFilesystem->getRootPath();
        if (!file_exists($dataDir)) {
            throw new \Exception("$dataDir not found");
        }
        if (!is_dir($dataDir)) {
            throw new \Exception("$dataDir is not a directory");
        }

        /*
         * Create git client according to parameters
         */
        $clientOptions = new ClientOptions();
        $clientOptions->setUrl($input->getArgument('url'));
        $token = $input->getArgument('token');
        $clientOptions->setToken($token);

        $type = $input->getOption('type');
        if (!empty($type)) {
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
        if (!empty($orgs)) {
            $findOptions->setOrganizations(explode(',', $orgs));
        }
        /* users option */
        $users = $input->getOption('users');
        if (!empty($users)) {
            $findOptions->setUsers(explode(',', $users));
        }

        /*
         * Find projects
         */
        $projects = $client->find($findOptions);

        foreach ($projects as $project) {
            $logger->info(sprintf(
                '[{%s}] %s ...',
                $project->getName(),
                $project->getHttpUrl()
            ));
            try {
                $this->fetchOrClone($project, $dataDir, $token);
            }catch(Exception $e){
                $logger->error(sprintf(
                    '[{%s}] %s : "%s"',
                    $project->getName(),
                    $project->getHttpUrl(),
                    $e->getMessage()
                ));
            }
        }

        $logger->info('[git:fetch-all] completed');

        return self::SUCCESS;
    }

    /**
     * 
     */
    protected function fetchOrClone(ProjectInterface $project, string $dataDir, ?string $token){
        $projectUrl = $project->getHttpUrl();

        // Compute local path according to url
        $host = parse_url($projectUrl, PHP_URL_HOST);
        $localPath = $dataDir.'/'.$host.'/'.$project->getName();

        // Inject token in url to clone repository
        $cloneUrl = $projectUrl;
        if (!empty($token)) {
            $scheme = parse_url($projectUrl, PHP_URL_SCHEME);
            $cloneUrl = str_replace("$scheme://", "$scheme://user-token:$token@", $projectUrl);
        }

        /*
         * fetch or clone repository to localPath
         */
        if (file_exists($localPath)) {
            $gitRepository = new GitRepository($localPath);
            // use token to fetch
            $gitRepository->run('remote', [
                'set-url',
                'origin',
                $cloneUrl,
            ]);
            // update local repository
            $gitRepository->run('fetch', ['origin', '--prune', '--prune-tags']);
            // remove token
            $gitRepository->run('remote', [
                'set-url',
                'origin',
                $projectUrl,
            ]);
        } else {
            GitAdmin::cloneTo($localPath, $cloneUrl, false);
            $gitRepository = new GitRepository($localPath);
            // remove token
            $gitRepository->run('remote', [
                'set-url',
                'origin',
                $projectUrl,
            ]);
        }
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
