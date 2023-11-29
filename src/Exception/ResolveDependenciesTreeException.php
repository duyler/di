<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ResolveDependenciesTreeException extends Exception implements ContainerExceptionInterface {}
