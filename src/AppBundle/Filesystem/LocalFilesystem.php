<?php

namespace AppBundle\Filesystem;

use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\Adapter\Local as LocalAdapter;


/**
 * Local data directory
 */
class LocalFilesystem extends LeagueFilesystem {

    /**
     * @var string
     */
    private $rootPath;

    public function __construct($dataDir){
        parent::__construct(new LocalAdapter($dataDir));
        $this->rootPath = $dataDir;
    }

    /**
     * @return string
     */
    public function getRootPath(){
        return $this->rootPath;
    }

}