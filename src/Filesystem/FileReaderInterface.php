<?php

namespace MBO\GitManager\Filesystem;

/**
 * Read files identified by an absolute path.
 *
 * Allows the replacement of the file access in unit tests.
 */
interface FileReaderInterface
{
    /**
     * Test if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Get the content of a file, null if it is missing or unreadable.
     */
    public function read(string $path): ?string;
}
