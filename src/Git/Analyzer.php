<?php

namespace MBO\GitManager\Git;

use Gitonomy\Git\Repository as GitRepository;

/**
 * Analyze git repository to provide informations
 */
class Analyzer {

    /**
     * Get metadata for a given repository
     *
     * @param string $repositoryName
     * @return array
     */
    public function getMetadata(GitRepository $gitRepository){
        $workingDir = $gitRepository->getWorkingDir();

        $metadata = array(
            'size' => $gitRepository->getSize() * 1024
        );

        $metadata['tags'] = $this->getTagNames($gitRepository);
        $metadata['branch'] = $this->getBranchNames($gitRepository);
        $metadata['activity'] = $this->getCommitDates($gitRepository);

        /* test README.md */
        $readmePath = $workingDir.DIRECTORY_SEPARATOR.'README.md';
        $metadata['readme'] = file_exists($readmePath);

        /* test composer.json */
        $composerPath = $workingDir.DIRECTORY_SEPARATOR.'composer.json';
        $metadata['php_composer'] = file_exists($composerPath);

        /* test composer.json */
        $pomPath = $workingDir.DIRECTORY_SEPARATOR.'pom.xml';
        $metadata['maven'] = file_exists($pomPath);

        /* test package.json */
        $packagePath = $workingDir.DIRECTORY_SEPARATOR.'package.json';
        $metadata['npm_package'] = file_exists($packagePath);

        //TODO add facets
        return $metadata;
    }

    /**
     * Get tag names
     *
     * @param GitRepository $gitRepository
     * @return string[]
     */
    private function getTagNames(GitRepository $gitRepository){
        $result = array();
        foreach ( $gitRepository->getReferences()->getTags() as $tag){
            $result[] = $tag->getName();
        }
        return $result;
    }

    /**
     * Get branch names
     *
     * @param GitRepository $gitRepository
     * @return string[]
     */
    private function getBranchNames(GitRepository $gitRepository){
        $result = array();
        foreach ( $gitRepository->getReferences()->getBranches() as $branch){
            $result[] = $branch->getName();
        }
        return $result;
    }

    /**
     * Get commit dates
     *
     * @param GitRepository $gitRepository
     * @return array
     */
    private function getCommitDates(GitRepository $gitRepository){
        $result = array();
        foreach ( $gitRepository->getReferences()->getAll() as $reference ){
            $commit = $reference->getCommit();
            $day = $commit->getAuthorDate()->format('Ymd');
            $result[$day] = isset($result[$day]) ? $result[$day] + 1 : 1;
        }
        ksort($result);
        return $result;
    }

}