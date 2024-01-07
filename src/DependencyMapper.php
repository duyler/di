<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Exception\CircularReferenceException;
use Duyler\DependencyInjection\Exception\InterfaceMapNotFoundException;
use Duyler\DependencyInjection\Provider\ProviderInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class DependencyMapper
{
    private array $classMap = [];
    private array $dependencies = [];
    private array $providers = [];

    public function __construct(
        private readonly ReflectionStorage $reflectionStorage,
        private readonly ServiceStorage $serviceStorage,
    ) {}

    public function bind(array $classMap): void
    {
        $this->classMap = $classMap + $this->classMap;
    }

    public function getClassMap(): array
    {
        return $this->classMap;
    }

    public function addProvider(string $id, ProviderInterface $provider): void
    {
        $this->providers[$id] = $provider;
    }

    /**
     * @throws InterfaceMapNotFoundException
     */
    public function getBind(string $interface): string
    {
        if (isset($this->classMap[$interface])) {
            return $this->classMap[$interface];
        }

        throw new InterfaceMapNotFoundException($interface);
    }

    /**
     * @throws ReflectionException
     * @throws InterfaceMapNotFoundException
     * @throws CircularReferenceException
     */
    public function resolve(string $className): array
    {
        $this->dependencies = [];
        $this->prepareDependencies($className);

        return $this->dependencies;
    }

    /**
     * @throws InterfaceMapNotFoundException
     * @throws CircularReferenceException
     * @throws ReflectionException
     */
    protected function prepareDependencies(string $className): void
    {
        if (!$this->reflectionStorage->has($className)) {
            $this->reflectionStorage->set($className, new ReflectionClass($className));
        }

        if ($this->reflectionStorage->get($className)->isInterface()) {
            $className = $this->prepareInterface($this->reflectionStorage->get($className), $className);
        }

        $constructor = $this->reflectionStorage->get($className)->getConstructor();

        if (null !== $constructor && false === $this->serviceStorage->has($className)) {
            $this->buildDependencies($constructor, $className);
        }
    }

    /**
     * @throws InterfaceMapNotFoundException
     * @throws CircularReferenceException
     * @throws ReflectionException
     */
    protected function buildDependencies(ReflectionMethod $constructor, string $className): void
    {
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if (null === $type) {
                continue;
            }

            $paramClassName = $type->getName();

            if (false === class_exists($paramClassName) && false === interface_exists($paramClassName)) {
                continue;
            }

            $this->reflectionStorage->set($paramClassName, new ReflectionClass($paramClassName));

            $class = $this->reflectionStorage->get($paramClassName);

            $paramArgClassName = $param->getName();

            if ($class->isInterface()) {
                $this->prepareInterface($class, $className, $paramArgClassName);
                continue;
            }

            $depClassName = $class->getName();

            $this->resolveDependency($className, $depClassName, $paramArgClassName);
        }
    }

    /**
     * @throws InterfaceMapNotFoundException
     * @throws CircularReferenceException
     * @throws ReflectionException
     */
    protected function prepareInterface(ReflectionClass $interface, string $className, string $depArgName = ''): string
    {
        $depInterfaceName = $interface->getName();

        /** @var ProviderInterface|null $provider */
        $provider = $this->providers[$className] ?? null;

        $this->classMap[$depInterfaceName] ??= $provider?->bind()[$depInterfaceName] ?? null;

        if (array_key_exists($depArgName, $provider?->getArguments() ?? [])) {
            return '@' . $depArgName . '_be provided_in_' . $provider::class;
        }

        if (!isset($this->classMap[$depInterfaceName])) {
            throw new InterfaceMapNotFoundException($depInterfaceName);
        }

        $depClassName = $this->classMap[$depInterfaceName];

        $this->resolveDependency($className, $depClassName, $depArgName);

        return $depClassName;
    }

    /**
     * @throws ReflectionException
     * @throws InterfaceMapNotFoundException
     * @throws CircularReferenceException
     */
    protected function resolveDependency(string $className, string $depClassName, string $depArgName = ''): void
    {
        if (isset($this->dependencies[$depClassName][$className])) {
            throw new CircularReferenceException($className, $depClassName);
        }

        $this->dependencies[$className][$depArgName] = $depClassName;
        $this->prepareDependencies($depClassName);
    }
}
