<?php

namespace MBO\GitManager;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        $contents = require $this->getRootDir().'/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    public function getRootDir(): string
    {
        return dirname(__DIR__);
    }

    public function getProjectDir(): string
    {
        if (self::isPhar()) {
            return '.';
        } else {
            return parent::getProjectDir();
        }
    }

    public function getConfigDir(): string
    {
        return $this->getRootDir().'/config';
    }

    public static function isPhar(): bool
    {
        return strlen(\Phar::running()) > 0 ? true : false;
    }

    public function getVarDir(): string
    {
        if (self::isPhar()) {
            return getenv('HOME').'/.git-manager';
        } else {
            return $this->getProjectDir().'/var/';
        }
    }

    public function getCacheDir(): string
    {
        return $this->getVarDir().'/cache/'.$this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return $this->getVarDir().'/logs';
    }
}
