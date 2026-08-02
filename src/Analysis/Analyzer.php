<?php

namespace MBO\GitManager\Analysis;

use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Analysis\Checker\CheckerInterface;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Storage\GitRepositoryStore;
use Psr\Log\LoggerInterface;

/**
 * Analyze git repository to provide information.
 */
final class Analyzer
{
    /**
     * @param CheckerInterface[] $checkers the checkers to run, configured in services.yaml
     */
    public function __construct(
        private GitRepositoryStore $gitRepositoryStore,
        private array $checkers,
        private LoggerInterface $logger,
    ) {
    }

    public function analyze(Project $project): void
    {
        $fullName = $project->getFullName();
        $this->logger->info('[analyze] start analysis for project: {fullName}', [
            'fullName' => $fullName,
        ]);
        $gitRepository = $this->gitRepositoryStore->getGitRepository($project->getFullName());

        $project->setMetadata($this->collectMetadata($gitRepository));
        $project->setChecks($this->runChecks($project));
    }

    /**
     * Collect repository metadata :
     * - size : the size of the repository
     * - tags : git tags
     * - activity : number of commits per day
     *
     * @return array<string,mixed>
     */
    private function collectMetadata(GitRepository $gitRepository): array
    {
        $metadata = [];
        $metadata['size'] = $gitRepository->getSize() * 1024;
        $metadata['tags'] = $this->getTagNames($gitRepository);
        $metadata['activity'] = $this->getActivity($gitRepository);

        return $metadata;
    }

    /**
     * Run checkers collecting results.
     *
     * @return array<string,mixed>
     */
    private function runChecks(Project $project): array
    {
        $checks = [];
        foreach ($this->checkers as $checker) {
            $checks[$checker->getName()] = $checker->check($project);
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
