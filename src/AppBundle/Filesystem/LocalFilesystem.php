<?php

namespace AppBundle\Filesystem;

use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\Adapter\Local as LocalAdapter;
use Psr\Log\LoggerInterface;


/**
 * Local data directory
 */
class LocalFilesystem extends LeagueFilesystem {

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        $dataDir,
        LoggerInterface $logger
    ){
        parent::__construct(new LocalAdapter($dataDir));
        $logger->info(sprintf("[LocalFilesystem] %s ",$dataDir));
        $this->rootPath = $dataDir;
        $this->logger   = $logger;
    }

    /**
     * @return string
     */
    public function getRootPath(){
        return $this->rootPath;
    }

    /**
     * Get local repositories
     * @return string[]
     */
    public function getRepositories(){
        $repositories = array();
        $this->findRepositories($repositories,'');
        return $repositories;
    }

    /**
     * Recursive .git finder
     *
     * @param array $repositories
     * @param string $parentPath
     * @return void
     */
    private function findRepositories(array & $repositories, $directory){
        $this->logger->debug(sprintf("[LocalFilesystem] findRepositories(%s)... ",$directory));

        try {
            $items = $this->listContents($directory);
        }catch(\Exception $e){
            $this->logger->info(sprintf(
                "[LocalFilesystem] findRepositories(%s) : can't list %s",
                $directory,
                $e->getMessage()
            ));
            return;
        }

        foreach ( $items as $item ){
            if ( 'dir' !== $item['type'] ){
                continue;
            }
            if ( $item['basename'] === '.git' ){
                $this->logger->debug(sprintf(
                    "[LocalFilesystem] findRepositories(%s) : found %s",
                    $directory,
                    $item['path']
                ));
                $repositories[] = $directory ;
                continue;
            }else{
                $this->findRepositories($repositories,$item['path']);
            }
            
        }
    }

}