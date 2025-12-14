<?php

namespace MBO\GitManager\Git;

use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Helpers\ProjectHelpers;
use MBO\RemoteGit\ProjectInterface;
use Psr\Log\LoggerInterface;

use Gitonomy\Git\Admin as GitAdmin;
use Gitonomy\Git\Repository as GitRepository;

class Synchronizer {

    public function __construct(
        private LocalFilesystem $localFilesystem,
        private LoggerInterface $logger
    ){

    }


    public function fetchOrClone(ProjectInterface $project, ?string $token): void
    {
        /*
         * Inject token in url to clone repository
         */
        $projectUrl = $project->getHttpUrl();
        $cloneUrl = $projectUrl;
        if (!empty($token)) {
            $scheme = parse_url($projectUrl, PHP_URL_SCHEME);
            $cloneUrl = str_replace("$scheme://", "$scheme://user-token:$token@", $projectUrl);
        }

        /*
        * fetch or clone repository to localPath
        */
        $fullName = ProjectHelpers::getFullName($project);
        $localPath = $this->localFilesystem->getRootPath().'/'.$fullName;
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


}

