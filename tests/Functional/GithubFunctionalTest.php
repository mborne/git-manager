<?php

namespace App\Tests\Functional;

use MBO\GitManager\Filesystem\LocalFilesystem;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class GithubFunctionalTest extends KernelTestCase
{
    public function testCommandFetchAll(): void
    {
        $kernel = self::bootKernel();

        $localFilesystem = self::getContainer()->get(LocalFilesystem::class);
        $this->assertInstanceOf(LocalFilesystem::class, $localFilesystem);
        $this->assertStringEndsWith('test-data', $localFilesystem->getRootPath());

        $application = new Application($kernel);

        $command = $application->find('git:fetch-all');
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
    }
}
