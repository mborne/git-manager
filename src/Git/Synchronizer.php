<?php

namespace MBO\GitManager\Git;

use Gitonomy\Git\Admin as GitAdmin;
use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Helpers\ProjectHelpers;
use MBO\RemoteGit\ProjectInterface;
use Psr\Log\LoggerInterface;

class Synchronizer
{
    public function __construct(
        private LocalFilesystem $localFilesystem,
        private string $askPassPath,
        private LoggerInterface $logger,
    ) {
        if (!is_executable($askPassPath)) {
            throw new \RuntimeException(sprintf("GIT_ASKPATH ('%s') is not runnable!", $askPassPath));
        }
    }

    public function fetchOrClone(ProjectInterface $project, ?string $token): void
    {
        /*
         * Inject token in url to clone repository
         */
        $projectUrl = $project->getHttpUrl();

        $env = [];
        if (!empty($token)) {
            $env['GIT_MANAGER_TOKEN'] = $token;
            $env['GIT_ASKPASS'] = $this->askPassPath;
            $env['GIT_TERMINAL_PROMPT'] = '0';
        }
        $options = [
            'environment_variables' => $env,
        ];

        /*
        * fetch or clone repository to localPath
        */
        $fullName = ProjectHelpers::getFullName($project);
        $localPath = $this->localFilesystem->getGitRepositoryPath($fullName);
        if (file_exists($localPath)) {
            $this->logger->debug(sprintf('%s already exists -> fetch and reset', $fullName));
            $gitRepository = new GitRepository($localPath, $options);
            // use token to fetch
            $gitRepository->run('remote', [
                'set-url',
                'origin',
                $projectUrl,
            ]);
            // update local repository
            $gitRepository->run('fetch', ['origin', '--prune', '--prune-tags']);
            // reset to default branch
            $gitRepository->run('reset', ['--hard', 'origin/'.$project->getDefaultBranch()]);
        } else {
            $this->logger->debug(sprintf("%s doesn't exists -> clone", $fullName));
            GitAdmin::cloneTo($localPath, $projectUrl, false, $options);
        }
    }
}
