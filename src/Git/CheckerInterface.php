<?php

namespace MBO\GitManager\Git;

use Gitonomy\Git\Repository as GitRepository;

/**
 * Interface to perform a check on a git repository
 */
interface CheckerInterface
{
    /**
     * Get name of the checker
     */
    public function getName(): string ;
    /**
     * Performs the check on the given repository
     */
    public function check(GitRepository $gitRepository): mixed ;
}
