<?php

namespace MBO\GitManager\Helpers;

/**
 * Build links to the source files hosted by a git hosting service.
 *
 * Note that only github.com is supported so far, null being returned for the other hosts.
 */
final class SourceUrlHelpers
{
    /**
     * Get the URL of a file for a given commit
     * (ex : "https://github.com/mborne/git-manager/blob/{commitSha}/README.md#L10").
     *
     * @param string      $httpUrl       the URL of the project (ex : "https://github.com/mborne/git-manager")
     * @param string      $path          the path of the file relative to the repository
     * @param string|null $commitSha     the commit containing the file
     * @param int|null    $startLine     an optional line to highlight
     * @param string|null $defaultBranch fallback branch used when commitSha is null
     *
     * @return string|null null if the host is not supported or if neither commitSha nor defaultBranch is available
     */
    public static function getFileUrl(
        string $httpUrl,
        string $path,
        ?string $commitSha = null,
        ?int $startLine = null,
        ?string $defaultBranch = null,
    ): ?string {
        $ref = $commitSha ?: $defaultBranch;
        if ('' === $path || null === $ref || '' === $ref) {
            return null;
        }
        if ('github.com' !== parse_url($httpUrl, PHP_URL_HOST)) {
            return null;
        }

        $baseUrl = rtrim($httpUrl, '/');
        if (str_ends_with($baseUrl, '.git')) {
            $baseUrl = substr($baseUrl, 0, -4);
        }

        $result = $baseUrl.'/blob/'.rawurlencode($ref).'/'.self::encodePath($path);
        if (null !== $startLine && $startLine > 0) {
            if (str_ends_with(strtolower($path), '.md')) {
                $result .= '?plain=1';
            }
            $result .= '#L'.$startLine;
        }

        return $result;
    }

    /**
     * Encode a path preserving the separators (note that gitleaks may report
     * windows paths such as "config\settings.yaml").
     */
    private static function encodePath(string $path): string
    {
        $parts = explode('/', trim(str_replace('\\', '/', $path), '/'));

        return implode('/', array_map('rawurlencode', $parts));
    }
}
