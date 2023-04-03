<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

class ContainerBuilder
{
    public static function build(): Container
    {
        $reflectionStorage = new ReflectionStorage();
        $serviceStorage = new ServiceStorage();
        return new Container(new Compiler($serviceStorage), new DependencyMapper($reflectionStorage), $serviceStorage);
    }
}
