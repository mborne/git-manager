<?php

namespace App\Tests\Helpers;

use MBO\GitManager\Helpers\SourceUrlHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceUrlHelpersTest extends TestCase
{
    public function testGetFileUrl(): void
    {
        $this->assertEquals(
            'https://github.com/mborne/git-manager/blob/0123456789abcdef/config/settings.yaml#L12',
            SourceUrlHelpers::getFileUrl(
                'https://github.com/mborne/git-manager',
                'config/settings.yaml',
                '0123456789abcdef',
                12
            )
        );
    }

    /**
     * The line is optional (ex : the region is missing in the report).
     */
    public function testGetFileUrlWithoutLine(): void
    {
        $this->assertEquals(
            'https://github.com/mborne/git-manager/blob/0123456789abcdef/README.md',
            SourceUrlHelpers::getFileUrl(
                'https://github.com/mborne/git-manager',
                'README.md',
                '0123456789abcdef'
            )
        );
    }

    /**
     * The trailing slash and the ".git" suffix are removed, the path is encoded
     * and the windows separators are converted.
     */
    public function testGetFileUrlNormalization(): void
    {
        $this->assertEquals(
            'https://github.com/mborne/git-manager/blob/0123456789abcdef/config/sub%20dir/settings.yaml#L1',
            SourceUrlHelpers::getFileUrl(
                'https://github.com/mborne/git-manager.git/',
                'config\\sub dir\\settings.yaml',
                '0123456789abcdef',
                1
            )
        );
    }

    /**
     * @return array<string,array<int,mixed>>
     */
    public static function provideUnsupportedCases(): array
    {
        return [
            'unsupported host' => ['https://gitlab.com/mborne/sample', 'README.md', '0123456789abcdef'],
            'missing commit' => ['https://github.com/mborne/sample', 'README.md', null],
            'empty commit' => ['https://github.com/mborne/sample', 'README.md', ''],
            'empty path' => ['https://github.com/mborne/sample', '', '0123456789abcdef'],
        ];
    }

    #[DataProvider('provideUnsupportedCases')]
    public function testGetFileUrlNotSupported(string $httpUrl, string $path, ?string $commitSha): void
    {
        $this->assertNull(
            SourceUrlHelpers::getFileUrl($httpUrl, $path, $commitSha)
        );
    }
}
