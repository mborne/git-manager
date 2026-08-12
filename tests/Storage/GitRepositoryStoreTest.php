<?php

namespace App\Tests\Storage;

use MBO\GitManager\Storage\GitRepositoryStore;
use MBO\GitManager\Storage\GitRepositoryStoreException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class GitRepositoryStoreTest extends TestCase
{
    private const FULLNAME = 'github.com/mborne/sample';

    private string $dataDir;

    private GitRepositoryStore $gitRepositoryStore;

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir().'/git-manager-repository-store-'.uniqid();
        $this->gitRepositoryStore = new GitRepositoryStore($this->dataDir, new NullLogger());
    }

    protected function tearDown(): void
    {
        (new SymfonyFilesystem())->remove($this->dataDir);
    }

    public function testGetPath(): void
    {
        $this->assertSame(
            $this->dataDir.'/'.self::FULLNAME,
            $this->gitRepositoryStore->getPath(self::FULLNAME)
        );
    }

    public function testExists(): void
    {
        $this->assertFalse($this->gitRepositoryStore->exists(self::FULLNAME));

        $this->createRepository(self::FULLNAME);

        $this->assertTrue($this->gitRepositoryStore->exists(self::FULLNAME));
    }

    public function testRemove(): void
    {
        $this->createRepository(self::FULLNAME);
        $this->createRepository('github.com/mborne/other');

        $this->gitRepositoryStore->remove(self::FULLNAME);

        $this->assertDirectoryDoesNotExist($this->gitRepositoryStore->getPath(self::FULLNAME));
        // the other repositories are preserved, as well as the parent directories
        $this->assertDirectoryExists($this->gitRepositoryStore->getPath('github.com/mborne/other'));
        $this->assertDirectoryExists($this->gitRepositoryStore->getPath('github.com/mborne'));
    }

    /**
     * Ensures that removing a missing repository is not an error (the purge must
     * be able to clean the database of an already removed repository).
     */
    public function testRemoveMissingRepository(): void
    {
        $this->gitRepositoryStore->remove(self::FULLNAME);

        $this->assertDirectoryDoesNotExist($this->gitRepositoryStore->getPath(self::FULLNAME));
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideInvalidFullnames(): iterable
    {
        yield 'empty' => [''];
        yield 'root' => ['/'];
        yield 'host only' => ['github.com'];
        yield 'leading slash' => ['/github.com/mborne/sample'];
        yield 'trailing slash' => ['github.com/mborne/sample/'];
        yield 'empty part' => ['github.com//sample'];
        yield 'current directory' => ['github.com/mborne/.'];
        yield 'parent directory' => ['github.com/mborne/../..'];
    }

    /**
     * Ensures that the removal can't reach the data directory itself.
     */
    #[DataProvider('provideInvalidFullnames')]
    public function testRemoveInvalidFullname(string $fullname): void
    {
        $this->createRepository(self::FULLNAME);

        $this->expectException(GitRepositoryStoreException::class);
        try {
            $this->gitRepositoryStore->remove($fullname);
        } finally {
            $this->assertDirectoryExists($this->gitRepositoryStore->getPath(self::FULLNAME));
        }
    }

    /**
     * Create a fake git repository in the data directory.
     */
    private function createRepository(string $fullname): void
    {
        (new SymfonyFilesystem())->dumpFile(
            $this->gitRepositoryStore->getPath($fullname).'/.git/HEAD',
            'ref: refs/heads/master'
        );
    }
}
