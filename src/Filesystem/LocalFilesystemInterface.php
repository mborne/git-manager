<?php

namespace MBO\GitManager\Filesystem;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Entity\Project;

/**
 * Resolve paths in the local data directory.
 *
 * Allows the replacement of the path resolution in unit tests.
 */
interface LocalFilesystemInterface
{
    /**
     * Get path to root directory.
     */
    public function getRootPath(): string;

    /**
     * Get GIT repository's path for a project given by its fullname.
     */
    public function getGitRepositoryPath(string $fullname): string;

    /**
     * Get GitRepository for a project given by its fullname.
     */
    public function getGitRepository(string $fullname): GitRepository;

    /**
     * Get path for the trivy report.
     */
    public function getTrivyReportPath(Project $project): string;
}
