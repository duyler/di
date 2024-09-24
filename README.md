![build](https://github.com/duyler/di/workflows/build/badge.svg)
# The dependency injection container

### This package makes it possible to quickly connect the DI-container to your project.

The container can be used both in automatic mode, using type hints in class constructors, and accept fine-tuning using providers.


**Example automatically make instance**

```php
use Duyler\DI\Container;
use YourClass;

$container = new Container;

$yourClassObject = $container->get(YouClass::class);

```

**Make instance with provider**

```php

class YourClass
{
    private MyClassInterface $myImplements;
    
    public function __construct(MyClassInterface $myImplements)
    {
        $this->myImplements = $myImplements;
    }
}

```

```php
use Duyler\DI\Provider\AbstractProvider

class ClassProvider extends AbstractProvider
{
    public function bind(): array
    {
        return [
            MyClassInterface::class => MyImplemensClass::class,
        ];
    }
}

```

```php

$container->addProviders([
    MyClassInterface::class => ClassProvider::class,
]);

$yourClassObject = $container->get(YourClass::class);

```

**Make instance with bind**

```php

$container->bind([
    MyClassInterface::class => MyImplemensClass::class,
]);

$yourClassObject = $container->get(YouClass::class);

```
