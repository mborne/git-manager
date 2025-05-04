<?php

namespace App\Tests\Helpers;

use MBO\GitManager\Helpers\ProjectHelpers;
use MBO\RemoteGit\ProjectInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProjectHelpersTest extends TestCase
{
    private function getMockProject(): ProjectInterface|MockObject
    {
        $project = $this->getMockBuilder(ProjectInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

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
