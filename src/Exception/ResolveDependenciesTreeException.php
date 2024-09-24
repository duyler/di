<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ResolveDependenciesTreeException extends Exception implements ContainerExceptionInterface {}
