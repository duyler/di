<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

class ContainerBuilder
{
    public static function build(): Container
    {
        $reflectionStorage = new ReflectionStorage();
        return new Container(new Compiler(), new DependencyMapper($reflectionStorage));
    }
}
