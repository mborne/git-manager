<?php

namespace App\Tests\Functional;

use MBO\GitManager\Storage\GitRepositoryStore;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class GithubFunctionalTest extends KernelTestCase
{
    public function testCommandFetch(): void
    {
        $kernel = self::bootKernel();

        $gitRepositoryStore = self::getContainer()->get(GitRepositoryStore::class);
        $this->assertInstanceOf(GitRepositoryStore::class, $gitRepositoryStore);

        $repositoryPath = $gitRepositoryStore->getPath('github.com/mborne/ansible-docker-ce');
        // safety net : the tests must not write in the real data directory
        $this->assertStringContainsString('var/test-data', $repositoryPath);

        $application = new Application($kernel);

        $command = $application->find('git:fetch');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'url' => 'https://github.com',
            '--users' => 'mborne',
            '--include' => '(ansible)',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString(
            'https://github.com/mborne/ansible-docker-ce.git',
            $output
        );

        $this->assertDirectoryExists($repositoryPath.'/.git');
    }
}
