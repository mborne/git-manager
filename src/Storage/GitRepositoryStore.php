<?php

namespace MBO\GitManager\Storage;

use Gitonomy\Git\Repository as GitRepository;
use Psr\Log\LoggerInterface;

/**
 * Store the git repositories cloned from the remote git hosting services in
 * the data directory, one directory per project ("{dataDir}/{fullname}").
 *
 * Note that the storage is local as the git repositories are read by git
 * itself and by the analysis tools (gitleaks, trivy,...).
 */
final readonly class GitRepositoryStore
{
    public function __construct(
        private string $dataDir,
        LoggerInterface $logger,
    ) {
        $logger->info(sprintf('[GitRepositoryStore] %s ', $dataDir));
    }

    /**
     * Get the path of the git repository of a project given by its fullname.
     */
    public function getPath(string $fullname): string
    {
        return $this->dataDir.DIRECTORY_SEPARATOR.$fullname;
    }

    /**
     * Get the git repository of a project given by its fullname.
     */
    public function getGitRepository(string $fullname): GitRepository
    {
        return new GitRepository($this->getPath($fullname));
    }
}
