<?php

namespace MBO\GitManager\Storage;

use Gitonomy\Git\Repository as GitRepository;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerInterface;

/**
 * Store the git repositories cloned from the remote git hosting services in
 * the data directory, one directory per project ("{dataDir}/{fullname}").
 *
 * Note that the storage is local as the git repositories are read by git
 * itself and by the analysis tools (gitleaks, trivy,...).
 */
final readonly class GitRepositoryStore
{
    /**
     * The fullnames are composed of at least two parts ("{host}/{name}"), the
     * name itself containing the namespaces of the project.
     */
    private const FULLNAME_REGEXP = '#^[^/]+(/[^/]+)+$#';

    private FilesystemOperator $filesystem;

    public function __construct(
        private string $dataDir,
        LoggerInterface $logger,
    ) {
        $logger->info(sprintf('[GitRepositoryStore] %s ', $dataDir));
        /* the data directory is created by the clones, not by this constructor */
        $this->filesystem = new Filesystem(new LocalFilesystemAdapter($dataDir, lazyRootCreation: true));
    }

    /**
     * Get the path of the git repository of a project given by its fullname.
     */
    public function getPath(string $fullname): string
    {
        return $this->dataDir.DIRECTORY_SEPARATOR.$fullname;
    }

    /**
     * Get the git repository of a project given by its fullname.
     */
    public function getGitRepository(string $fullname): GitRepository
    {
        return new GitRepository($this->getPath($fullname));
    }

    /**
     * Test if the git repository of a project is available in the data directory.
     */
    public function exists(string $fullname): bool
    {
        try {
            return $this->filesystem->directoryExists($fullname);
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * Remove the git repository of a project given by its fullname, ignoring a
     * missing repository.
     *
     * @throws GitRepositoryStoreException if the fullname is invalid or if the
     *                                     repository can't be removed
     */
    public function remove(string $fullname): void
    {
        $this->ensureIsFullname($fullname);
        try {
            $this->filesystem->deleteDirectory($fullname);
        } catch (FilesystemException $e) {
            throw new GitRepositoryStoreException(sprintf('fail to remove the git repository %s : %s', $fullname, $e->getMessage()), previous: $e);
        }
    }

    /**
     * Ensure that a fullname designates a single project so that a removal
     * can't reach the data directory itself or one of its parents.
     *
     * @throws GitRepositoryStoreException if the fullname is invalid
     */
    private function ensureIsFullname(string $fullname): void
    {
        $parts = explode('/', $fullname);
        if (1 !== preg_match(self::FULLNAME_REGEXP, $fullname) || [] !== array_intersect(['.', '..'], $parts)) {
            throw new GitRepositoryStoreException(sprintf('"%s" is not a valid project fullname (ex : "github.com/mborne/git-manager")', $fullname));
        }
    }
}
