<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use MBO\GitManager\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class GithubFunctionalTest extends KernelTestCase
{
    /**
     * @var string
     */
    private $gitManagerDir;

    public function setUp(): void
    {
        $this->gitManagerDir = getenv('GIT_MANAGER_DIR');
        $this->assertNotFalse($this->gitManagerDir);
        $this->assertStringEndsWith('git-manager-test', $this->gitManagerDir);
    }

    public function testCommandFetchAll(): void
    {
        // cleanup GIT_MANAGER_DIR
        $fs = new Filesystem();
        $fs->remove($this->gitManagerDir);

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('git:fetch-all');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'url' => 'https://github.com',
            '--users' => 'mborne',
            '--include' => '(ansible)',
        ], [
            'verbosity' => ConsoleOutput::VERBOSITY_DEBUG,
        ]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertFileExists(
            $this->gitManagerDir.'/github.com/mborne/ansible-docker-ce'
        );
    }

    /**
     * @depends testCommandFetchAll
     */
    public function testCommandStats(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('git:stats');
        $commandTester = new CommandTester($command);
        $commandTester->execute([], [
            'verbosity' => ConsoleOutput::VERBOSITY_DEBUG,
        ]);

        $commandTester->assertCommandIsSuccessful();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $projectRepository = $entityManager->getRepository(Project::class);
        /** @var Project $project */
        $project = $projectRepository->findOneBy(['name' => 'github.com/mborne/ansible-docker-ce']);
        $this->assertNotNull($project);
    }
}
