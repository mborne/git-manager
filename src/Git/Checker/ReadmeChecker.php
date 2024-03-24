<?php

namespace MBO\GitManager\Git\Checker;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Git\CheckerInterface;
use Psr\Log\LoggerInterface;

/**
 * Ensure that README file is present.
 */
class ReadmeChecker implements CheckerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getName(): string
    {
        return 'readme';
    }

    public function check(GitRepository $gitRepository): bool
    {
        $this->logger->debug('[{checker}] look for README.md file...', [
            'checker' => $this->getName(),
            'repository' => $gitRepository->getWorkingDir(),
        ]);

        $workingDir = $gitRepository->getWorkingDir();
        $readmePath = $workingDir.DIRECTORY_SEPARATOR.'README.md';

        return file_exists($readmePath);
    }
}
