<?php

namespace MBO\GitManager\Git;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Filesystem\LocalFilesystem;
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
        private LocalFilesystem $localFilesystem,
        bool $trivyEnabled,
        private LoggerInterface $logger
    ) {
        $this->checkers = [
            new ReadmeChecker($logger),
            new LicenseChecker($logger),
            new TrivyChecker($trivyEnabled, $logger),
        ];
    }

    /**
     * Update a given projet using local data.
     */
    public function update(Project $project): void
    {
        $this->logger->debug('[Analyser] update %s...', [
            'repository' => $project->getName(),
        ]);
        $gitRepository = new GitRepository(
            $this->localFilesystem->getRootPath().'/'.$project->getName()
        );
        $project->setUpdatedAt(new \DateTime('now'));
        $project->setSize($gitRepository->getSize() * 1024);
        $project->setTags($this->getTagNames($gitRepository));
        $project->setBranches($this->getBranchNames($gitRepository));
        $project->setActivity($this->getCommitDates($gitRepository));

        $project->setChecks($this->getChecks($gitRepository));
    }

    /**
     * @return array<string,mixed>
     */
    private function getChecks(GitRepository $gitRepository): array
    {
        $results = [];
        foreach ($this->checkers as $checker) {
            $results[$checker->getName()] = $checker->check($gitRepository);
        }

        return $results;
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
