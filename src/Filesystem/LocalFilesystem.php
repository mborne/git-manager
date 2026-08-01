<?php

namespace MBO\GitManager\Filesystem;

use Gitonomy\Git\Repository as GitRepository;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerInterface;

/**
 * Local data directory.
 */
final class LocalFilesystem extends LeagueFilesystem implements LocalFilesystemInterface
{
    public function __construct(
        private string $dataDir,
        LoggerInterface $logger,
    ) {
        parent::__construct(new LocalFilesystemAdapter($dataDir));
        $logger->info(sprintf('[LocalFilesystem] %s ', $dataDir));
    }

    /**
     * Get path to root directory.
     */
    public function getRootPath(): string
    {
        return $this->dataDir;
    }

    /**
     * Get GIT repository's path for a project given by its fullname.
     */
    public function getGitRepositoryPath(string $fullname): string
    {
        return $this->dataDir.DIRECTORY_SEPARATOR.$fullname;
    }

    /**
     * Get GitRepository for a project given by its fullname.
     */
    public function getGitRepository(string $fullname): GitRepository
    {
        return new GitRepository($this->getGitRepositoryPath($fullname));
    }
}
