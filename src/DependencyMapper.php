<?php

declare(strict_types=1);

namespace Duyler\DI;

use Duyler\DI\Exception\CircularReferenceException;
use Duyler\DI\Exception\InterfaceBindNotFoundException;
use Duyler\DI\Exception\InterfaceMapNotFoundException;
use Duyler\DI\Provider\ProviderInterface;
use Duyler\DI\Storage\ProviderArgumentsStorage;
use Duyler\DI\Storage\ProviderFactoryServiceStorage;
use Duyler\DI\Storage\ProviderStorage;
use Duyler\DI\Storage\ReflectionStorage;
use Duyler\DI\Storage\ServiceStorage;
use ReflectionClass;
use ReflectionMethod;

final class DependencyMapper
{
    /** @var array<string, string> */
    private array $classMap = [];

    /** @var array<string, array<string, string>> */
    private array $dependencies = [];

    /** @var array<string, string> */
    private array $mainServiceLog = [];

    /** @var array<string, string> */
    private array $repeatedServiceLog = [];

    public function __construct(
        private readonly ReflectionStorage $reflectionStorage,
        private readonly ServiceStorage $serviceStorage,
        private readonly ProviderStorage $providerStorage,
        private readonly ProviderArgumentsStorage $argumentsStorage,
        private readonly ContainerService $containerService,
        private readonly ProviderFactoryServiceStorage $providerFactoryServiceStorage,
    ) {}

    public function bind(array $classMap): void
    {
        $this->classMap = $classMap + $this->classMap;
    }

    public function getClassMap(): array
    {
        return $this->classMap;
    }

    /**
     * @throws InterfaceBindNotFoundException
     */
    public function getBind(string $interface): string
    {
        if ($this->providerStorage->has($interface)) {
            $provider = $this->providerStorage->get($interface);
            $this->classMap = $provider->bind() + $this->classMap;
            if (isset($this->classMap[$interface])) {
                $this->providerStorage->add($this->classMap[$interface], $provider);
            }
        }

        return $this->classMap[$interface] ?? throw new InterfaceBindNotFoundException($interface);
    }

    public function resolve(string $className): array
    {
        $this->dependencies = [];
        $this->repeatedServiceLog = [];
        $this->mainServiceLog = [];
        $this->prepareDependencies($className);

        return $this->dependencies;
    }

    private function prepareDependencies(string $className): void
    {
        if (false === $this->reflectionStorage->has($className)) {
            $this->reflectionStorage->set($className, new ReflectionClass($className));
        }

        $constructor = $this->reflectionStorage->get($className)->getConstructor();

        if (null !== $constructor && false === $this->serviceStorage->has($className)) {
            if ($this->providerStorage->has($className)) {
                $provider = $this->providerStorage->get($className);
                $arguments = $this->prepareProviderArguments($provider, $className);

                $service = $provider->factory($this->containerService);

                if (null !== $service) {
                    $this->dependencies[$className] = [];
                    $this->providerFactoryServiceStorage->add($className, $service);
                    return;
                }

                if (count($constructor->getParameters()) === count($arguments)) {
                    $this->dependencies[$className] = [];
                    return;
                }
            }
            $this->buildDependencies($constructor, $className);
        }
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    private function buildDependencies(ReflectionMethod $constructor, string $className): void
    {
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if (null === $type) {
                continue;
            }

            $paramClassName = (string) $type->getName();

            if (false === class_exists($paramClassName)
                && false === interface_exists($paramClassName)
                || enum_exists($paramClassName)
            ) {
                continue;
            }

            $this->reflectionStorage->set($paramClassName, new ReflectionClass($paramClassName));

            $reflectionClass = $this->reflectionStorage->get($paramClassName);

            $paramArgClassName = $param->getName();

            if ($this->providerStorage->has($className)) {
                $provider = $this->providerStorage->get($className);

                if (array_key_exists($paramArgClassName, $this->prepareProviderArguments($provider, $className))) {
                    continue;
                }
            }

            if ($reflectionClass->isInterface()) {
                $this->prepareInterface($reflectionClass, $className, $paramArgClassName);
                continue;
            }

            $depClassName = $reflectionClass->getName();

            $this->resolveDependency($className, $depClassName, $paramArgClassName);
        }
    }

    private function prepareProviderArguments(ProviderInterface $provider, string $className): array
    {
        $arguments = $provider->getArguments($this->containerService);
        $this->argumentsStorage->set($className, $arguments);
        return $arguments;
    }

    /**
     * @throws InterfaceMapNotFoundException
     */
    private function prepareInterface(ReflectionClass $interface, string $className, string $depArgName): void
    {
        $depInterfaceName = $interface->getName();

        if ($this->providerStorage->has($className)) {
            $provider = $this->providerStorage->get($className);
            $this->classMap[$depInterfaceName] ??= $provider->bind()[$depInterfaceName] ?? null;
        }

        if ($this->providerStorage->has($depInterfaceName)) {
            $provider = $this->providerStorage->get($depInterfaceName);
            $this->classMap[$depInterfaceName] ??= $provider->bind()[$depInterfaceName] ?? null;
        }

        if ($this->providerStorage->has($depInterfaceName)) {
            $provider = $this->providerStorage->get($depInterfaceName);
            $service = $provider->factory($this->containerService);

            if (null !== $service) {
                $this->argumentsStorage->set($className, [$depArgName => $service]);
                return;
            }
        }

        if (!isset($this->classMap[$depInterfaceName])) {
            throw new InterfaceMapNotFoundException($depInterfaceName, $className);
        }

        /** @var string $depClassName */
        $depClassName = $this->classMap[$depInterfaceName];

        $this->resolveDependency($className, $depClassName, $depArgName);
    }

    private function resolveDependency(string $className, string $depClassName, string $depArgName): void
    {
        $this->resolveCycleDependencies($className, $depClassName);
        $this->prepareDependencies($depClassName);
        $this->dependencies[$className][$depArgName] = $depClassName;
    }

    /**
     * @throws CircularReferenceException
     */
    private function resolveCycleDependencies(string $className, string $depClassName): void
    {
        if (in_array($depClassName, $this->mainServiceLog)) {
            $this->repeatedServiceLog[$depClassName] = $depClassName;
        } else {
            $this->mainServiceLog[$depClassName] = $depClassName;
        }

        if (count($this->repeatedServiceLog) === count($this->mainServiceLog)) {
            if ([] === array_diff($this->mainServiceLog, $this->repeatedServiceLog)) {
                throw new CircularReferenceException($className, $depClassName);
            }
        }
    }
}
