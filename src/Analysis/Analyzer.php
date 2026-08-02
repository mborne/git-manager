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
     * - tags_count : the number of git tags
     * - last_tag : the name of the last git tag
     * - last_activity : the most recent day with a commit
     *
     * @return array<string,mixed>
     */
    private function collectMetadata(GitRepository $gitRepository): array
    {
        $tagNames = $this->getTagNames($gitRepository);

        $metadata = [];
        $metadata['size'] = $gitRepository->getSize() * 1024;
        $metadata['tags_count'] = \count($tagNames);
        $metadata['last_tag'] = empty($tagNames) ? null : end($tagNames);
        $metadata['last_activity'] = $this->getLastActivity($gitRepository);

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
     * Get tag names sorted by git (alphabetical order).
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
     * Get the date of the most recent commit (format : RFC3339 as fetchedAt,
     * null if there is no commit). Only the commits referenced by a branch or a
     * tag are considered.
     */
    private function getLastActivity(GitRepository $gitRepository): ?string
    {
        $result = null;
        foreach ($gitRepository->getReferences()->getAll() as $reference) {
            /* note that gitonomy parses the author dates in UTC */
            $authorDate = $reference->getCommit()->getAuthorDate();
            if (null === $result || $authorDate > $result) {
                $result = $authorDate;
            }
        }

        return null === $result ? null : $result->format(\DateTimeInterface::RFC3339);
    }
}
