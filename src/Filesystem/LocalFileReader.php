<?php

namespace MBO\GitManager\Filesystem;

/**
 * Read files from the local filesystem.
 */
final class LocalFileReader implements FileReaderInterface
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function read(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return false === $content ? null : $content;
    }
}
