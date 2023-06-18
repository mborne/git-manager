<?php

namespace MBO\GitManager\Filesystem;

use Exception;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerInterface;

/**
 * Local data directory.
 */
class LocalFilesystem extends LeagueFilesystem
{
    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        $dataDir,
        LoggerInterface $logger
    ) {
        parent::__construct(new LocalFilesystemAdapter($dataDir));
        $logger->info(sprintf('[LocalFilesystem] %s ', $dataDir));
        $this->rootPath = $dataDir;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getRootPath()
    {
        return $this->rootPath;
    }

    /**
     * Get local repositories.
     *
     * @return string[]
     */
    public function getRepositories()
    {
        $repositories = [];
        $this->findRepositories($repositories, '');

        return $repositories;
    }

    /**
     * Recursive .git finder.
     *
     * @param string $parentPath
     *
     * @return void
     */
    private function findRepositories(array &$repositories, $directory)
    {
        $this->logger->debug(sprintf('[LocalFilesystem] findRepositories(%s)... ', $directory));

        // test if current directory is a git repository
        if ( $this->isGitRepository($directory) ){
            $this->logger->debug(sprintf(
                '[LocalFilesystem] findRepositories(%s) : found',
                $directory
            ));
            $repositories[] = $directory;
            return;
        }

        // else, scan sub directories
        $items = $this->listContents($directory);
        foreach ($items as $item) {
            if ('dir' !== $item->type()) {
                continue;
            }
            $this->findRepositories($repositories, $item->path());
        }
    }

    /**
     * Test if directory contains .git subfolder
     */
    private function isGitRepository(string $directory): bool {
        return $this->directoryExists($directory.'/.git');
    }
}
