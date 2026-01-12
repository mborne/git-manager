<?php

namespace MBO\GitManager\Filesystem;

use Gitonomy\Git\Repository as GitRepository;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MBO\GitManager\Entity\Project;
use Psr\Log\LoggerInterface;

/**
 * Local data directory.
 */
class LocalFilesystem extends LeagueFilesystem
{
    public function __construct(
        private string $dataDir,
        LoggerInterface $logger,
    ) {
        parent::__construct(new LocalFilesystemAdapter($dataDir));
        $logger->info(sprintf('[LocalFilesystem] %s ', $dataDir));
        if (!$this->directoryExists('.trivy')) {
            $logger->info('create .trivy directory');
            $this->createDirectory('.trivy');
        }
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

    /**
     * Get path for the trivy report.
     */
    public function getTrivyReportPath(Project $project): string
    {
        return implode(
            DIRECTORY_SEPARATOR,
            [$this->getRootPath(), '.trivy', (string) $project->getId().'.json']
        );
    }
}
