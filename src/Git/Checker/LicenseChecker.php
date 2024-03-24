<?php

namespace MBO\GitManager\Git\Checker;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Git\CheckerInterface;
use Psr\Log\LoggerInterface;

/**
 * Ensure that LICENSE file is present.
 */
class LicenseChecker implements CheckerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public const LICENSE_FILENAMES = [
        'LICENSE',
        'LICENSE.md',
    ];

    public function getName(): string
    {
        return 'license';
    }

    public function check(GitRepository $gitRepository): bool|string
    {
        $this->logger->debug('[{checker}] look for license file...', [
            'checker' => $this->getName(),
            'repository' => $gitRepository->getWorkingDir(),
            'expected' => static::LICENSE_FILENAMES,
        ]);

        $workingDir = $gitRepository->getWorkingDir();
        foreach (static::LICENSE_FILENAMES as $filename) {
            $expectedPath = $workingDir.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($expectedPath)) {
                return $filename;
            }
        }

        return false;
    }
}
