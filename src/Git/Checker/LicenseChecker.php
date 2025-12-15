<?php

namespace MBO\GitManager\Git\Checker;

use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\LocalFilesystem;
use MBO\GitManager\Git\CheckerInterface;
use Psr\Log\LoggerInterface;

/**
 * Ensure that LICENSE file is present.
 */
class LicenseChecker implements CheckerInterface
{
    public function __construct(
        private LocalFilesystem $localFilesystem,
        private LoggerInterface $logger,
    ) {
    }

    public const LICENSE_FILENAMES = [
        'LICENSE',
        'LICENSE.md',
    ];

    public function getName(): string
    {
        return 'license';
    }

    public function check(Project $project): bool|string
    {
        $repositoryPath = $this->localFilesystem->getGitRepositoryPath($project->getFullName());
        $this->logger->debug('[{checker}] look for license file...', [
            'checker' => $this->getName(),
            'repository' => $project->getFullName(),
            'expected' => static::LICENSE_FILENAMES,
        ]);

        foreach (static::LICENSE_FILENAMES as $filename) {
            $expectedPath = $repositoryPath.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($expectedPath)) {
                return $filename;
            }
        }

        return false;
    }
}
