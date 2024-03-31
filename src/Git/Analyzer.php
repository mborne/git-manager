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
        $project->setMetadata($this->getMetadata($gitRepository));
    }

    /**
     * Get metadata for a given repository.
     *
     * @return array<string,mixed>
     */
    private function getMetadata(GitRepository $gitRepository): array
    {
        $this->logger->debug('[Analyser] retrieve git metadata...', [
            'repository' => $gitRepository->getWorkingDir(),
        ]);
        $metadata = [
            'updatedAt' => new \DateTime('now'),
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
