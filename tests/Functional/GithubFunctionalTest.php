<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString(
            'https://github.com/mborne/git-manager.git',
            $output
        );
    }

    /**
     * @depends testCommandFetchAll
     */
    public function testCommandStats()
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('git:stats');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString(
            '[info] save stats : /tmp/git-manager-test/repositories.json',
            $output
        );

        $this->assertFileExists('/tmp/git-manager-test/repositories.json');
    }
}
