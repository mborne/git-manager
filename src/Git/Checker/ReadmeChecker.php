<?php

namespace MBO\GitManager\Git\Checker;

use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Git\CheckerInterface;
use Psr\Log\LoggerInterface;

/**
 * Ensure that README file is present.
 */
class ReadmeChecker implements CheckerInterface
{
    public function __construct(
        private LocalFilesystem $localFilesystem,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'readme';
    }

    public function check(Project $project): bool
    {
        $repositoryPath = $this->localFilesystem->getGitRepositoryPath($project->getFullName());
        $this->logger->debug('[{checker}] look for README.md file...', [
            'checker' => $this->getName(),
            'repository' => $project->getFullName(),
        ]);

        $readmePath = $repositoryPath.DIRECTORY_SEPARATOR.'README.md';

        return file_exists($readmePath);
    }
}
