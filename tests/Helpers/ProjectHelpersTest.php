<?php

namespace App\Tests\Helpers;

use MBO\GitManager\Helpers\ProjectHelpers;
use MBO\RemoteGit\ProjectInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ProjectHelpersTest extends TestCase
{
    private function getMockProject(): ProjectInterface|Stub
    {
        $project = $this->createStub(ProjectInterface::class);

        $project
            ->method('getHttpUrl')
            ->willReturn('https://mborne.github.com/mborne/remote-git')
        ;
        $project
            ->method('getName')
            ->willReturn('mborne/remote-git')
        ;

        return $project;
    }

    public function testGetFullName(): void
    {
        $project = $this->getMockProject();
        $this->assertEquals(
            'mborne.github.com/mborne/remote-git',
            ProjectHelpers::getFullName($project)
        );
    }
}
