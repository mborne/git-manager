<?php

namespace App\Tests\Analysis\Checker;

use MBO\GitManager\Analysis\Checker\LicenseChecker;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Storage\GitRepositoryStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class LicenseCheckerTest extends TestCase
{
    private const FULLNAME = 'github.com/mborne/sample';

    private string $dataDir;

    private LicenseChecker $licenseChecker;

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir().'/git-manager-license-checker-'.uniqid();
        $this->licenseChecker = new LicenseChecker(
            new GitRepositoryStore($this->dataDir, new NullLogger()),
            new NullLogger()
        );
    }

    protected function tearDown(): void
    {
        (new SymfonyFilesystem())->remove($this->dataDir);
    }

    public function testGetName(): void
    {
        $this->assertSame('license', $this->licenseChecker->getName());
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideLicenseFilenames(): iterable
    {
        foreach (LicenseChecker::LICENSE_FILENAMES as $filename) {
            yield $filename => [$filename];
        }
    }

    /**
     * Ensures that each allowed license file name is detected and reported.
     */
    #[DataProvider('provideLicenseFilenames')]
    public function testLicenseFound(string $filename): void
    {
        $this->createRepository([$filename]);

        $this->assertSame($filename, $this->licenseChecker->check($this->createProject()));
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideNonLicenseFilenames(): iterable
    {
        yield 'readme' => ['README.md'];
        yield 'lowercase' => ['license.txt'];
        yield 'unsupported extension' => ['LICENSE.rst'];
        yield 'suffixed' => ['LICENSE-MIT'];
    }

    /**
     * Ensures that the other file names are not reported as licenses.
     */
    #[DataProvider('provideNonLicenseFilenames')]
    public function testLicenseNotFound(string $filename): void
    {
        $this->createRepository([$filename]);

        $this->assertFalse($this->licenseChecker->check($this->createProject()));
    }

    /**
     * Ensures that the result doesn't depend on the order of the directory entries.
     */
    public function testMultipleLicenses(): void
    {
        $this->createRepository(['README.md', 'LICENCE.md', 'LICENSE.txt']);

        $this->assertSame('LICENSE.txt', $this->licenseChecker->check($this->createProject()));
    }

    /**
     * Ensures that a missing repository is not an error (ex : the analysis of a
     * project removed from the data directory).
     */
    public function testMissingRepository(): void
    {
        $this->assertFalse($this->licenseChecker->check($this->createProject()));
    }

    private function createProject(): Project
    {
        $project = new Project();
        $project->setFullName(self::FULLNAME);

        return $project;
    }

    /**
     * Create a fake git repository containing the given files.
     *
     * @param string[] $filenames
     */
    private function createRepository(array $filenames): void
    {
        $filesystem = new SymfonyFilesystem();
        $repositoryPath = $this->dataDir.'/'.self::FULLNAME;
        $filesystem->dumpFile($repositoryPath.'/.git/HEAD', 'ref: refs/heads/master');
        foreach ($filenames as $filename) {
            $filesystem->dumpFile($repositoryPath.'/'.$filename, 'content');
        }
    }
}
