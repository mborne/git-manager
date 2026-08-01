<?php

namespace App\Tests\Unit\Filesystem;

use MBO\GitManager\Filesystem\TempFilesystem;
use PHPUnit\Framework\TestCase;

final class TempFilesystemTest extends TestCase
{
    private TempFilesystem $tempFilesystem;

    protected function setUp(): void
    {
        $this->tempFilesystem = new TempFilesystem();
    }

    public function testGetPathIsInTheTemporaryDirectory(): void
    {
        $path = $this->tempFilesystem->getPath('gitleaks', 'sarif');

        $this->assertMatchesRegularExpression(
            '#^'.preg_quote(sys_get_temp_dir(), '#').'.gitleaks-[0-9a-f]{16}\.sarif$#',
            $path
        );
    }

    public function testGetPathDoesNotCreateTheFile(): void
    {
        $this->assertFileDoesNotExist($this->tempFilesystem->getPath('gitleaks', 'sarif'));
    }

    public function testGetPathIsUnique(): void
    {
        $this->assertNotSame(
            $this->tempFilesystem->getPath('gitleaks', 'sarif'),
            $this->tempFilesystem->getPath('gitleaks', 'sarif')
        );
    }

    public function testRemove(): void
    {
        $path = $this->tempFilesystem->getPath('gitleaks', 'sarif');
        file_put_contents($path, '{"runs":[]}');

        $this->tempFilesystem->remove($path);

        $this->assertFileDoesNotExist($path);
    }

    public function testRemoveMissingFile(): void
    {
        $path = $this->tempFilesystem->getPath('gitleaks', 'sarif');

        $this->tempFilesystem->remove($path);

        $this->assertFileDoesNotExist($path);
    }
}
