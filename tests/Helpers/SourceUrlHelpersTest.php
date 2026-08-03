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
     * Markdown files use ?plain=1 so that line anchors work on GitHub.
     */
    public function testGetFileUrlMarkdownWithLine(): void
    {
        $this->assertEquals(
            'https://github.com/mborne/git-manager/blob/0123456789abcdef/doc/conventions/manuals-tests.md?plain=1#L144',
            SourceUrlHelpers::getFileUrl(
                'https://github.com/mborne/git-manager',
                'doc/conventions/manuals-tests.md',
                '0123456789abcdef',
                144
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
     * When commitSha is null but defaultBranch is provided, the branch is used.
     */
    public function testGetFileUrlWithDefaultBranch(): void
    {
        $this->assertEquals(
            'https://github.com/mborne/git-manager/blob/main/README.md?plain=1#L5',
            SourceUrlHelpers::getFileUrl(
                'https://github.com/mborne/git-manager',
                'README.md',
                null,
                5,
                'main'
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
            'missing commit and branch' => ['https://github.com/mborne/sample', 'README.md', null],
            'empty commit and no branch' => ['https://github.com/mborne/sample', 'README.md', ''],
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
