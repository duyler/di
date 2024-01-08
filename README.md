![build](https://github.com/duyler/dependency-injection/workflows/build/badge.svg)
# The dependency injection container

### This package makes it possible to quickly connect the DI-container to your project.

The container can be used both in automatic mode, using type hints in class constructors, and accept fine-tuning using providers.


**Example automatically make instance**

```
use Duyler\DependencyInjection\Container;
use YourClass;

$container = new Container;

$yourClassObject = $container->get(YouClass::class);

```

**Make instance with provider**

```

class YourClass
{
    private MyClassInterface $myImplements;
    
    public function __construct(MyClassInterface $myImplements)
    {
        $this->myImplements = $myImplements;
    }
}

```

```
use Duyler\DependencyInjection\Provider\AbstractProvider

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

```

$container->addProviders([
    YouClass::class => ClassProvider::class,
]);

$yourClassObject = $container->get(YouClass::class);

```

**Make instance with bind**

```

$container->bind([
    MyClassInterface::class => MyImplemensClass::class,
]);

$yourClassObject = $container->get(YouClass::class);

```
