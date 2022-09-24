<?php

namespace MBO\GitManager;

use Phar;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    private const CONFIG_EXTS = '.{php,yaml,yml}';

    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir().'/config/bundles.php';
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

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->addResource(new FileResource($this->getProjectDir().'/config/bundles.php'));
        $container->setParameter('container.dumper.inline_class_loader', \PHP_VERSION_ID < 70400 || $this->debug);
        $container->setParameter('container.dumper.inline_factories', true);
        $confDir = $this->getProjectDir().'/config';

        $loader->load($confDir.'/{packages}/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{packages}/'.$this->environment.'/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}_'.$this->environment.self::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/{routes}/'.$this->environment.'/*.yaml');
        $routes->import('../config/{routes}/*.yaml');
    }
}
