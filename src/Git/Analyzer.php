<?php

namespace MBO\GitManager\Git;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Git\Checker\CheckerInterface;
use MBO\GitManager\Git\Checker\LicenseChecker;
use MBO\GitManager\Git\Checker\ReadmeChecker;

/**
 * Analyze git repository to provide informations.
 */
class Analyzer
{
    /**
     * @var CheckerInterface[]
     */
    private $checkers;

    public function __construct()
    {
        $this->checkers = [
            new ReadmeChecker(),
            new LicenseChecker(),
        ];
    }
    /**
     * Get metadata for a given repository.
     *
     * @param string $repositoryName
     *
     * @return array
     */
    public function getMetadata(GitRepository $gitRepository)
    {
        $metadata = [
            'size' => $gitRepository->getSize() * 1024,
        ];

        $metadata['tags'] = $this->getTagNames($gitRepository);
        $metadata['branch'] = $this->getBranchNames($gitRepository);
        $metadata['activity'] = $this->getCommitDates($gitRepository);

        foreach ($this->checkers as $checker) {
            $metadata[$checker->getName()] = $checker->check($gitRepository);
        }

        return $metadata;
    }

    /**
     * Get tag names.
     *
     * @return string[]
     */
    private function getTagNames(GitRepository $gitRepository)
    {
        $result = [];
        foreach ($gitRepository->getReferences()->getTags() as $tag) {
            $result[] = $tag->getName();
        }

        return $result;
    }

    /**
     * Get branch names.
     *
     * @return string[]
     */
    private function getBranchNames(GitRepository $gitRepository)
    {
        $result = [];
        foreach ($gitRepository->getReferences()->getBranches() as $branch) {
            $result[] = $branch->getName();
        }

        return $result;
    }

    /**
     * Get commit dates.
     *
     * @return array
     */
    private function getCommitDates(GitRepository $gitRepository)
    {
        $result = [];
        foreach ($gitRepository->getReferences()->getAll() as $reference) {
            $commit = $reference->getCommit();
            $day = $commit->getAuthorDate()->format('Ymd');
            $result[$day] = isset($result[$day]) ? $result[$day] + 1 : 1;
        }
        ksort($result);

        return $result;
    }
}
