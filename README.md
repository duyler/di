![build](https://github.com/konveyer-framework/dependency-injection/workflows/build/badge.svg)
# The dependency injection container

### This package makes it possible to quickly connect the DI-container to your project.

The container can be used both in automatic mode, using type hints in class constructors, and accept fine-tuning using providers.

**Example**

```
use Konveyer\DependencyInjection\ContainerBuilder;
use YourClass;`

$container = ContainerBuilder->build();

$yourClassObject = $container->make(YouClass::class);

