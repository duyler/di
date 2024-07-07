<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Exception;

use Exception;

class ServiceForFinalizeNotFoundException extends Exception
{
    public function __construct(string $class)
    {
        parent::__construct('Service for finalize ' . $class . ' not defined in container');
    }
}
