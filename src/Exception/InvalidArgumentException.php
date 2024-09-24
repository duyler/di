<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Psr\Container\ContainerExceptionInterface;

class InvalidArgumentException extends \InvalidArgumentException implements ContainerExceptionInterface {}
