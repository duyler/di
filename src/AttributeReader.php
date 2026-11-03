<?php

declare(strict_types=1);

namespace Duyler\DI;

use Duyler\DI\Attribute\Bind;
use Duyler\DI\Attribute\Finalize;
use Duyler\DI\Attribute\Tag;
use Duyler\DI\Attribute\Transient;
use Duyler\DI\Storage\ReflectionStorage;
use ReflectionClass;

final class AttributeReader
{
    /**
    * @var array<string, bool>
    */
    private array $hasAttributesCache = [];

    public function __construct(
        private readonly ReflectionStorage $reflectionStorage,
    ) {}

    /**
    * @param class-string $className
    */
    private function reflection(string $className): ReflectionClass
    {
        if (false === $this->reflectionStorage->has($className)) {
            $this->reflectionStorage->set($className, new ReflectionClass($className));
        }

        return $this->reflectionStorage->get($className);
    }

    /**
    * @param class-string $className
    */
    public function getScope(string $className): ?Scope
    {
        $reflection = $this->reflection($className);

        if ($reflection->getAttributes(Transient::class)) {
            return Scope::Transient;
        }

        return null;
    }

    /**
    * @param class-string $className
    * @return array<string>
    */
    public function getTags(string $className): array
    {
        $reflection = $this->reflection($className);
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
        $reflection = $this->reflection($className);
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
        if (isset($this->hasAttributesCache[$className])) {
            return $this->hasAttributesCache[$className];
        }

        $reflection = $this->reflection($className);

        return $this->hasAttributesCache[$className] = !empty($reflection->getAttributes(Transient::class))
            || !empty($reflection->getAttributes(Finalize::class))
            || !empty($reflection->getAttributes(Tag::class))
            || !empty($reflection->getAttributes(Bind::class));
    }

    /**
    * @param class-string $className
    * @return array<class-string>
    */
    public function getInterfaces(string $className): array
    {
        $reflection = $this->reflection($className);

        return $reflection->getInterfaceNames();
    }
}
