<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Cache\CacheHandlerInterface;

readonly class Config
{
    public function __construct(
        public string $cacheDirPath = '',
        public ?CacheHandlerInterface $cacheExternalHandler = null,
    ) {
    }
}
