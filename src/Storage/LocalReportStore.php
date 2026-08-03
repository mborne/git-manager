<?php

namespace MBO\GitManager\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use Symfony\Component\Uid\Uuid;

/**
 * Store the reports as JSON files in the local data directory
 * ("{dataDir}/reports/{toolName}/{projectId}.json").
 */
final readonly class LocalReportStore implements ReportStoreInterface
{
    /**
     * Name of the parent directory containing all the reports.
     */
    private const REPORTS_DIR = 'reports';

    /**
     * Extension of the report files.
     */
    private const EXTENSION = '.json';

    private FilesystemOperator $filesystem;

    public function __construct(string $dataDir)
    {
        $this->filesystem = new Filesystem(new LocalFilesystemAdapter(
            $dataDir.DIRECTORY_SEPARATOR.self::REPORTS_DIR
        ));
    }

    public function list(string $toolName): array
    {
        try {
            $filenames = $this->filesystem
                ->listContents($toolName)
                ->filter(fn (StorageAttributes $item): bool => $item->isFile())
                ->map(fn (StorageAttributes $item): string => basename($item->path()))
                ->toArray()
            ;
        } catch (FilesystemException $e) {
            throw new ReportStoreException(sprintf('fail to list the reports of %s : %s', $toolName, $e->getMessage()), previous: $e);
        }

        $projectIds = [];
        foreach ($filenames as $filename) {
            if (!str_ends_with($filename, self::EXTENSION)) {
                continue;
            }
            $projectId = substr($filename, 0, -strlen(self::EXTENSION));
            if (!Uuid::isValid($projectId)) {
                continue;
            }
            $projectIds[] = Uuid::fromString($projectId);
        }

        return $projectIds;
    }

    public function exists(string $toolName, Uuid $projectId): bool
    {
        try {
            return $this->filesystem->fileExists($this->getPath($toolName, $projectId));
        } catch (FilesystemException) {
            return false;
        }
    }

    public function write(string $toolName, Uuid $projectId, string $content): void
    {
        $path = $this->getPath($toolName, $projectId);
        try {
            $this->filesystem->write($path, $content);
        } catch (FilesystemException $e) {
            throw new ReportStoreException(sprintf('fail to write the report %s : %s', $path, $e->getMessage()), previous: $e);
        }
    }

    public function read(string $toolName, Uuid $projectId): ?string
    {
        try {
            return $this->filesystem->read($this->getPath($toolName, $projectId));
        } catch (FilesystemException) {
            return null;
        }
    }

    /**
     * Get the path of a report relative to the data directory.
     */
    private function getPath(string $toolName, Uuid $projectId): string
    {
        return $toolName.'/'.(string) $projectId.self::EXTENSION;
    }
}
