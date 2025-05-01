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

        $project->setMetadata($this->collectMetadata($gitRepository));
        $project->setChecks($this->runChecks($gitRepository));
    }

    /**
     * Collect repository metadata :
     * - size : the size of the repository
     * - tags : git tags
     * - branches : the list of the branches
     * - activity : number of commits per day
     */
    private function collectMetadata(GitRepository $gitRepository){
        $metadata = [];
        $metadata['size'] = $gitRepository->getSize() * 1024;
        $metadata['tags'] = $this->getTagNames($gitRepository);
        $metadata['branches'] = $this->getBranchNames($gitRepository);
        $metadata['activity'] = $this->getActivity($gitRepository);
        return $metadata;
    }

    /**
     * Run checkers collecting results
     */
    private function runChecks(GitRepository $gitRepository){
        $checks = [];
        foreach ($this->checkers as $checker) {
            $checks[$checker->getName()] = $checker->check($gitRepository);
        }
        return $checks;
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
