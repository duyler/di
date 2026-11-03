<?php

declare(strict_types=1);

namespace Duyler\DI\Event;

final class ContainerEvents
{
    public const string BEFORE_RESOLVE = 'container.before_resolve';

    /**
     * Payload time is a float >= 0.0 (0.0 is legal for an instant resolution).
     * Dispatched on every successful get(). A cache hit dispatches once
     * (has-branch). A fresh creation through the default branch dispatches
     * twice: first from the internal get() with the raw service before
     * decorators, last from the resolution tail with the final decorated
     * service. Subscribers must treat the last event per service id as the
     * final one.
     */
    public const string AFTER_RESOLVE = 'container.after_resolve';

    /**
     * Dispatched only in the construction path make(); the transient path
     * (makeTransient) does not dispatch it. serviceId is the identifier
     * passed to get() — an interface name before binding resolution.
     */
    public const string BEFORE_MAKE = 'container.before_make';

    /**
     * Payload time is a float >= 0.0 (0.0 is legal for an instant resolution).
     * Dispatched only in the construction path make(); the transient path
     * (makeTransient) does not dispatch it. serviceId is the identifier
     * after binding resolution — an implementation class name when the
     * factory abstained (returned null).
     */
    public const string AFTER_MAKE = 'container.after_make';
    public const string BEFORE_FINALIZE = 'container.before_finalize';
    public const string AFTER_FINALIZE = 'container.after_finalize';
}
