<?php

namespace MBO\GitManager;

use Phar;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class Kernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new GitManagerBundle(),
        ];
    }

    public function getRootDir(): string
    {
        return dirname(__DIR__);
    }

    public function getProjectDir(): string
    {
        if ( self::isPhar() ){
            return '.';
        }else{
            return parent::getProjectDir();
        }
    }

    public static function isPhar(): bool
    {
        return strlen(Phar::running()) > 0 ? true : false;
    }

    public function getVarDir(): string
    {
        if ( self::isPhar() ){
            return getenv('HOME').'/.git-manager';
        }else{
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

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->setParameter('container.autowiring.strict_mode', true);
            $container->setParameter('container.dumper.inline_class_loader', true);

            $container->addObjectResource($this);
        });
        $loader->load($this->getRootDir().'/config/config.yml');
    }
}
