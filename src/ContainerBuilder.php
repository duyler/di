<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Cache\FileCacheHandler;

class ContainerBuilder
{
    public static function build(Config $config = null): Container
    {
        if ($config?->cacheExternalHandler) {
            $cacheHandler = $config->cacheExternalHandler;
        } else {
            $cacheHandler = new FileCacheHandler(
                $config?->cacheDirPath ?: dirname('__DIR__'). '/../var/cache/container/'
            );
        }

        $reflectionStorage = new ReflectionStorage();
        $serviceStorage = new ServiceStorage();
        $dependencyMapper = new DependencyMapper($reflectionStorage);
        return new Container(new Compiler($serviceStorage), $dependencyMapper, $serviceStorage, $cacheHandler);
    }
}
