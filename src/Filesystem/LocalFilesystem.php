<?php

namespace MBO\GitManager\Filesystem;

use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerInterface;

/**
 * Local data directory.
 */
class LocalFilesystem extends LeagueFilesystem
{
    public function __construct(
        private string $dataDir,
        LoggerInterface $logger,
    ) {
        parent::__construct(new LocalFilesystemAdapter($dataDir));
        $logger->info(sprintf('[LocalFilesystem] %s ', $dataDir));
    }

    /**
     * Get path to root directory.
     */
    public function getRootPath(): string
    {
        return $this->dataDir;
    }
}
