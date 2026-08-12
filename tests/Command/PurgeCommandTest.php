<?php

namespace App\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Repository\ProjectRepository;
use MBO\GitManager\Storage\GitRepositoryStore;
use MBO\GitManager\Storage\ReportStoreInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Uid\Uuid;

final class PurgeCommandTest extends KernelTestCase
{
    private const PROJECT_ID = '00000000-0000-3000-8000-000000000051';

    private const FULL_NAME = 'github.com/mborne/purge-sample';

    public function testPurgeProject(): void
    {
        $commandTester = $this->createCommandTester();

        $this->createProject();
        $repositoryPath = $this->getGitRepositoryStore()->getPath(self::FULL_NAME);
        $this->assertDirectoryExists($repositoryPath);

        $commandTester->execute(['projectId' => self::PROJECT_ID]);

        $commandTester->assertCommandIsSuccessful();

        /* the git repository is removed, the sibling ones are preserved */
        $this->assertDirectoryDoesNotExist($repositoryPath);
        $this->assertDirectoryExists($this->getGitRepositoryStore()->getPath('github.com/mborne/purge-other'));

        /* the reports are removed */
        $projectId = Uuid::fromString(self::PROJECT_ID);
        $reportStore = $this->getReportStore();
        $this->assertFalse($reportStore->exists(GitleaksChecker::NAME, $projectId));
        $this->assertFalse($reportStore->exists(TrivyChecker::NAME, $projectId));

        /* the project is removed from the database */
        $this->assertNull($this->findProject(self::FULL_NAME));
    }

    /**
     * Ensures that a project can be removed from the database when its git
     * repository is already missing.
     */
    public function testPurgeProjectWithoutGitRepository(): void
    {
        $commandTester = $this->createCommandTester();

        $this->createProject();
        (new SymfonyFilesystem())->remove($this->getGitRepositoryStore()->getPath(self::FULL_NAME));

        $commandTester->execute(['projectId' => self::PROJECT_ID]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('no git repository', $commandTester->getDisplay());
        $this->assertNull($this->findProject(self::FULL_NAME));
    }

    public function testPurgeUnknownProject(): void
    {
        $commandTester = $this->createCommandTester();

        $commandTester->execute(['projectId' => '00000000-0000-3000-8000-0000000000ff']);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('project not found in the database', $commandTester->getDisplay());
    }

    public function testPurgeInvalidProjectId(): void
    {
        $commandTester = $this->createCommandTester();

        $commandTester->execute(['projectId' => self::FULL_NAME]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('is not a valid project id', $commandTester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find('git:purge'));
    }

    private function getGitRepositoryStore(): GitRepositoryStore
    {
        $gitRepositoryStore = self::getContainer()->get(GitRepositoryStore::class);
        $this->assertInstanceOf(GitRepositoryStore::class, $gitRepositoryStore);

        // safety net : the tests must not remove anything in the real data directory
        $this->assertStringContainsString('var/test-data', $gitRepositoryStore->getPath(self::FULL_NAME));

        return $gitRepositoryStore;
    }

    private function getReportStore(): ReportStoreInterface
    {
        $reportStore = self::getContainer()->get(ReportStoreInterface::class);
        $this->assertInstanceOf(ReportStoreInterface::class, $reportStore);

        return $reportStore;
    }

    private function findProject(string $fullName): ?Project
    {
        $projectRepository = self::getContainer()->get(ProjectRepository::class);
        $this->assertInstanceOf(ProjectRepository::class, $projectRepository);

        return $projectRepository->findOneBy(['fullName' => $fullName]);
    }

    /**
     * Create a project with its git repository and its reports, together with a
     * sibling repository which must be preserved.
     */
    private function createProject(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $id = Uuid::fromString(self::PROJECT_ID);
        $project = $entityManager->find(Project::class, $id) ?? new Project();
        $project
            ->setId($id)
            ->setName('mborne/purge-sample')
            ->setFullName(self::FULL_NAME)
            ->setHttpUrl('https://github.com/mborne/purge-sample')
            ->setDescription('A project to purge')
            ->setDefaultBranch('master')
            ->setArchived(false)
            ->setVisibility('public')
            ->setFetchedAt(new \DateTime('2026-01-01 10:00:00'))
        ;
        $entityManager->persist($project);
        $entityManager->flush();

        $reportStore = $this->getReportStore();
        $reportStore->write(GitleaksChecker::NAME, $id, '{"runs":[]}');
        $reportStore->write(TrivyChecker::NAME, $id, '{"Results":[]}');

        $this->createGitRepository(self::FULL_NAME);
        $this->createGitRepository('github.com/mborne/purge-other');
    }

    /**
     * Create a fake git repository in the data directory.
     */
    private function createGitRepository(string $fullName): void
    {
        (new SymfonyFilesystem())->dumpFile(
            $this->getGitRepositoryStore()->getPath($fullName).'/.git/HEAD',
            'ref: refs/heads/master'
        );
    }
}
