<?php

declare(strict_types=1);

namespace Konveyer\DependencyInjection;

use Konveyer\DependencyInjection\Exception\EndlessException;
use Konveyer\DependencyInjection\Exception\InterfaceMapNotFoundException;
use ReflectionClass;
use ReflectionMethod;

class DependencyMapper
{
    private ReflectionStorage $reflectionStorage;
    private array $classMap = [];
    private array $dependencies = [];
    private array $providers = [];

    public function __construct(ReflectionStorage $reflectionStorage)
    {
        $this->reflectionStorage = $reflectionStorage;
    }

    public function bind(array $classMap): void
    {
        $this->classMap = $classMap + $this->classMap;
    }
    
    public function setProviders(array $providers): void
    {
        $this->providers = $providers;
    }

    public function getBind(string $interface): string
    {
        if (isset($this->classMap[$interface])) {
            return $this->classMap[$interface];
        }
        throw new InterfaceMapNotFoundException($interface);
    }

    public function resolve(string $className): array
    {
        $this->prepareDependencies($className);
        return $this->dependencies;
    }

    // Подготавливает зависимости к рекурсивному инстанцированию
    protected function prepareDependencies(string $className): void
    {
        // Проверяем наличие ранее созданного дерева зависимостей для класса
        if (isset($this->trees[$className])) {
            $this->dependencies = $this->trees[$className];
            return;
        }

        // Проверяем наличие ранее созданых рефлексий
        if (!$this->reflectionStorage->has($className)) {
            $this->reflectionStorage->set($className, new ReflectionClass($className));
        }

        if ($this->reflectionStorage->get($className)->isInterface()) {
            $this->prepareInterface($this->reflectionStorage->get($className), $className);
        }

        // Получаем конструктор
        $constructor = $this->reflectionStorage->get($className)->getConstructor();

        if ($constructor !== null) {
            $this->buildDependencies($constructor, $className);
        }
    }

    // Рекурсивно выстраивает массив (дерево) зависимостей
    protected function buildDependencies(ReflectionMethod $constructor, string $className): void
    {
        // Проходим по параметрам конструктора
        foreach ($constructor->getParameters() as $param) {

            // Получаем класс из подсказки типа
            $type = $param->getType();

            if ($type === null) {
                continue;
            }

            $paramClassName = $type->getName();

            // Хз, как правильно сделать. Нужны тесты
            if (class_exists($paramClassName) === false && interface_exists($paramClassName) === false) {
                continue;
            }


            $this->reflectionStorage->set($paramClassName, new ReflectionClass($paramClassName));

            $class = $this->reflectionStorage->get($paramClassName);

            $paramArgClassName = $param->getName();

            // Если в параметрах есть зависимость то получаем её
            if (null !== $class) {

                if ($class->isInterface()) {

                    $this->prepareInterface($class, $className, $paramArgClassName);
                    continue;
                }

                $depClassName = $class->getName();

                $this->resolveDependency($className, $depClassName, $paramArgClassName);
            }
        }
    }

    // Подготавливает интерфейс
    protected function prepareInterface(ReflectionClass $interface, string $className, string $depArgname = ''): void
    {
        $depInterfaceName = $interface->getName();

        $this->classMap[$depInterfaceName] ??= $this->providers[$className]?->bind()[$depInterfaceName];

        if (!isset($this->classMap[$depInterfaceName])) {
            throw new InterfaceMapNotFoundException($depInterfaceName);
        }

        $depClassName = $this->classMap[$depInterfaceName];

        $this->resolveDependency($className, $depClassName, $depArgname);
    }

    // Создает массив (дерево) зависимостей
    protected function resolveDependency(string $className, string $depClassName, string $depArgName = ''): void
    {
        // Если класс зависит от запрошенного то это циклическая зависимость
        if (isset($this->dependencies[$depClassName][$className])) {
            throw new EndlessException($className, $depClassName);
        }

        // Проверять на интерфейс
        $this->dependencies[$className][$depArgName] = $depClassName;
        $this->prepareDependencies($depClassName);
    }
}
