<?php

declare(strict_types=1);

namespace Duyler\DI;

use Duyler\DI\Attribute\Bind;
use Duyler\DI\Attribute\Singleton;
use Duyler\DI\Attribute\Tag;
use Duyler\DI\Attribute\Transient;
use ReflectionClass;

final class AttributeReader
{
    /**
     * @param class-string $className
     */
    public function getScope(string $className): ?Scope
    {
        $reflection = new ReflectionClass($className);

        if ($reflection->getAttributes(Transient::class)) {
            return Scope::Transient;
        }

        if ($reflection->getAttributes(Singleton::class)) {
            return Scope::Singleton;
        }

        return null;
    }

    /**
     * @param class-string $className
     * @return array<string>
     */
    public function getTags(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $tags = [];

        foreach ($reflection->getAttributes(Tag::class) as $attribute) {
            /** @var Tag $instance */
            $instance = $attribute->newInstance();
            $attributeTags = is_array($instance->tags) ? $instance->tags : [$instance->tags];
            $tags = array_merge($tags, $attributeTags);
        }

        return $tags;
    }

    /**
     * @param class-string $className
     * @return class-string|null
     */
    public function getBinding(string $className): ?string
    {
        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(Bind::class);

        if (empty($attributes)) {
            return null;
        }

        /** @var Bind $instance */
        $instance = $attributes[0]->newInstance();

        return $instance->interface;
    }

    /**
     * @param class-string $className
     */
    public function hasAttributes(string $className): bool
    {
        $reflection = new ReflectionClass($className);

        return !empty($reflection->getAttributes(Transient::class))
            || !empty($reflection->getAttributes(Singleton::class))
            || !empty($reflection->getAttributes(Tag::class))
            || !empty($reflection->getAttributes(Bind::class));
    }

    /**
     * @param class-string $className
     * @return array<class-string>
     */
    public function getInterfaces(string $className): array
    {
        $reflection = new ReflectionClass($className);

        return $reflection->getInterfaceNames();
    }
}
