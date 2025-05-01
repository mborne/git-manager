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
        private LoggerInterface $logger,
    ) {
        $this->checkers = [
            new ReadmeChecker($logger),
            new LicenseChecker($logger),
            new TrivyChecker($trivyEnabled, $logger),
        ];
    }

    public function analyze(Project $project): void
    {
        $fullName = $project->getFullName();
        $this->logger->info('[analyze] start analysis for project: {fullName}', [
            'fullName' => $fullName,
        ]);
        $gitRepository = new GitRepository(
            $this->localFilesystem->getRootPath().'/'.$fullName
        );
        $project->setSize($gitRepository->getSize() * 1024);
        $project->setTags($this->getTagNames($gitRepository));
        $project->setBranchNames($this->getBranchNames($gitRepository));
        $project->setActivity($this->getActivity($gitRepository));

        $checks = [];
        foreach ($this->checkers as $checker) {
            $checks[$checker->getName()] = $checker->check($gitRepository);
        }
        $project->setChecks($checks);
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
    private function getActivity(GitRepository $gitRepository): array
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
