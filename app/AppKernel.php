<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new MBO\GitManager\GitManagerBundle(),
        ];
        return $bundles;
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getProjectDir(){
        if ( self::isPhar() ){
            return '.';
        }else{
            return parent::getProjectDir();
        }
    }

    /**
     * @return boolean
     */
    public static function isPhar() {
        return strlen(Phar::running()) > 0 ? true : false;
    }

    public function getVarDir(){
        if ( self::isPhar() ){
            return getenv('HOME').'/.git-manager';
        }else{
            return $this->getProjectDir().'/var/';
        }
    }

    public function getCacheDir()
    {
        return $this->getVarDir().'/cache/'.$this->getEnvironment();
    }

    public function getLogDir()
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
