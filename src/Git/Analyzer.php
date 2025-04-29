<?php

namespace MBO\GitManager\Git;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Git\Checker\LicenseChecker;
use MBO\GitManager\Git\Checker\ReadmeChecker;
use MBO\GitManager\Git\Checker\TrivyChecker;
use Psr\Log\LoggerInterface;

/**
 * Analyze git repository to provide informations.
 */
class Analyzer
{
    /**
     * @var CheckerInterface[]
     */
    private $checkers;

    public function __construct(
        bool $trivyEnabled,
        private LoggerInterface $logger,
    ) {
        $this->checkers = [
            new ReadmeChecker($logger),
            new LicenseChecker($logger),
            new TrivyChecker($trivyEnabled, $logger),
        ];
    }

    /**
     * Get metadata for a given repository.
     *
     * @return array<string,mixed>
     */
    public function getMetadata(GitRepository $gitRepository): array
    {
        $this->logger->debug('[Analyser] retrieve git metadata...', [
            'repository' => $gitRepository->getWorkingDir(),
        ]);
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
    private function getTagNames(GitRepository $gitRepository): array
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
    private function getBranchNames(GitRepository $gitRepository): array
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
     * @return array<string,int>
     */
    private function getCommitDates(GitRepository $gitRepository): array
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
