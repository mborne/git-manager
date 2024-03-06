<?php

namespace MBO\GitManager\Git\Checker;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Git\CheckerInterface;

/**
 * Ensure that README file is present.
 */
class ReadmeChecker implements CheckerInterface
{
    public function getName(): string
    {
        return 'readme';
    }

    public function check(GitRepository $gitRepository): bool
    {
        $workingDir = $gitRepository->getWorkingDir();
        $readmePath = $workingDir.DIRECTORY_SEPARATOR.'README.md';

        return file_exists($readmePath);
    }
}
