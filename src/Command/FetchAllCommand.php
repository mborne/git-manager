<?php

namespace MBO\GitManager\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Git\Analyzer;
use MBO\GitManager\Git\Synchronizer;
use MBO\GitManager\Helpers\ProjectHelpers;
use MBO\GitManager\Repository\ProjectRepository;
use MBO\RemoteGit\ClientFactory;
use MBO\RemoteGit\ClientOptions;
use MBO\RemoteGit\Filter\FilterCollection;
use MBO\RemoteGit\Filter\IncludeRegexpFilter;
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
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ProjectRepository $projectRepository,
        private Synchronizer $synchronizer,
        private EntityManagerInterface $em,
        private LocalFilesystem $localFilesystem,
        private Analyzer $analyzer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
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
            ->addOption('users', 'u', InputOption::VALUE_REQUIRED, 'Find projects according to given user names')

            ->addOption('include', null, InputOption::VALUE_REQUIRED, 'Filter according to a given regexp, for ex : "(ansible)"')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = $this->createLogger($output);

        $logger->info('[git:fetch-all] started...');

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

        $filterCollection = new FilterCollection($logger);
        $findOptions->setFilter($filterCollection);

        /* include option */
        if (!empty($input->getOption('include'))) {
            $filterCollection->addFilter(new IncludeRegexpFilter(
                $input->getOption('include')
            ));
        }

        /*
         * Find projects
         */
        $projects = $client->getProjects($findOptions);

        foreach ($projects as $project) {
            $logger->info(sprintf(
                '[%s] %s ...',
                $project->getName(),
                $project->getHttpUrl()
            ));
            try {
                $this->synchronizer->fetchOrClone($project, $token);
                $entity = $this->createOrUpdateProjectEntity($project);
                $this->em->persist($entity);
                $this->em->flush();
            } catch (\Exception $e) {
                $logger->error(sprintf(
                    '[%s] %s : "%s"',
                    $project->getName(),
                    $project->getHttpUrl(),
                    $e->getMessage()
                ));
                $this->managerRegistry->resetManager();
            }
        }

        $logger->info('[git:fetch-all] completed');

        return self::SUCCESS;
    }

    /**
     * Create or update project entity based on the given project interface.
     */
    protected function createOrUpdateProjectEntity(ProjectInterface $project): Project
    {
        $uid = ProjectHelpers::getUid($project);
        /** @var Project|null */
        $entity = $this->projectRepository->findOneBy(['id' => $uid]);
        if (null === $entity) {
            $entity = new Project();
            $entity->setId($uid);
        }
        $entity
            ->setName($project->getName())
            ->setHttpUrl($project->getHttpUrl())
            ->setDefaultBranch($project->getDefaultBranch())
            ->setArchived($project->isArchived())
            ->setVisibility($project->getVisibility()?->toString())
            ->setFullName(ProjectHelpers::getFullName($project))
        ;

        $this->analyzer->analyze($entity);

        $entity->setFetchedAt(new \DateTime('now'));

        return $entity;
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
