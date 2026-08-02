<?php

namespace MBO\GitManager\Storage;

/**
 * Temporary directory used by the tools writing their output to a file.
 */
final class TempFilesystem
{
    /**
     * Get a unique path in the temporary directory.
     *
     * Note that the file itself is not created so that the callers can detect
     * that a tool has not produced its output.
     */
    public function getPath(string $prefix, string $extension): string
    {
        return sprintf(
            '%s%s%s-%s.%s',
            sys_get_temp_dir(),
            DIRECTORY_SEPARATOR,
            $prefix,
            bin2hex(random_bytes(8)),
            $extension
        );
    }

    /**
     * Remove a temporary file, ignoring a missing file.
     */
    public function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
