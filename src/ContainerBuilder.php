<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Cache\CacheHandlerInterface;
use Duyler\DependencyInjection\Cache\FileCacheHandler;
use RuntimeException;
use Override;

class ContainerBuilder
{
    public static function build(ContainerConfig $config = null): Container
    {
        if ($config?->enableCache) {
            if ($config?->cacheExternalHandler) {
                $cacheHandler = $config->cacheExternalHandler;
            } else {
                $cacheHandler = new FileCacheHandler(
                    $config?->fileCacheDirPath ?? new RuntimeException('FileCacheDirPath is not set'),
                );
            }
        } else {
            $cacheHandler = new class () implements CacheHandlerInterface {
                #[Override] public function isExists(string $id): bool
                {
                    return false;
                }

                #[Override] public function get(string $id): array
                {
                    return [];
                }

                #[Override] public function record(string $id, array $dependencyTree): void {}

                #[Override] public function invalidate(string $id): void {}
            };
        }

        $reflectionStorage = new ReflectionStorage();
        $serviceStorage = new ServiceStorage();
        $dependencyMapper = new DependencyMapper($reflectionStorage, $serviceStorage);

        return new Container(new Compiler($serviceStorage), $dependencyMapper, $serviceStorage, $cacheHandler);
    }
}
