<?php

declare(strict_types=1);

namespace Duyler\DI\Event;

final class ContainerEvents
{
    public const string BEFORE_RESOLVE = 'container.before_resolve';
    public const string AFTER_RESOLVE = 'container.after_resolve';
    public const string BEFORE_MAKE = 'container.before_make';
    public const string AFTER_MAKE = 'container.after_make';
    public const string BEFORE_FINALIZE = 'container.before_finalize';
    public const string AFTER_FINALIZE = 'container.after_finalize';
}
