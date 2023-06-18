<?php

namespace MBO\GitManager\Git\Checker;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Git\CheckerInterface;

/**
 * Ensure that README file is present
 */
class LicenseChecker implements CheckerInterface
{
    const LICENSE_FILENAMES = [
        'LICENSE',
        'LICENSE.md'
    ];

    function getName(): string
    {
        return 'license';
    }

    function check(GitRepository $gitRepository): bool|string {
        $workingDir = $gitRepository->getWorkingDir();
        foreach ( static::LICENSE_FILENAMES as $filename ){
            $expectedPath = $workingDir.DIRECTORY_SEPARATOR.$filename;
            if ( file_exists($expectedPath) ){
                return $filename;
            }
        }
        return false;
    }

}
