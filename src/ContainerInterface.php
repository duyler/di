<?php

declare(strict_types=1);

namespace Duyler\DI;

use Override;
use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * The identifier must be a trusted (internal) one; for untrusted input
     * enable strict resolution (ContainerConfig::withStrictResolution).
     */
    #[Override]
    public function get(string $id): mixed;

    /**
     * Returns true only for already instantiated services.
     * False does not mean get() cannot create the service via autowiring.
     */
    #[Override]
    public function has(string $id): bool;

    /**
     * Invalidates the dependency tree cache: trees are rebuilt lazily
     * on the next get(). Keys must be trusted (internal) identifiers;
     * for untrusted input enable strict resolution
     * (ContainerConfig::withStrictResolution).
     *
     * @param array<string, string> $classMap
     */
    public function bind(array $classMap): self;

    /**
     * Invalidates the dependency tree cache: trees are rebuilt lazily
     * on the next get().
     *
     * @param array<string, string> $providers
     */
    public function addProviders(array $providers): self;

    /**
     * @return array<string, string>
     */
    public function getClassMap(): array;

    /**
     * Replaces an existing service silently: the last set() wins.
     * References already injected into other services keep the old object.
     * Not applied to transient classes: the transient branch of get()
     * runs earlier.
     */
    public function set(object $definition): self;

    public function addDefinition(Definition $definition): self;

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function getDependencyTree(): array;

    /**
     * Resets runtime state: services, provider factory services, provider arguments,
     * factories, tags, decorators, event listeners, debug statistics,
     * dependency trees and finalizers. Configuration survives: providers,
     * scopes, reflection cache, interface bindings, auto-tagging and debug mode.
     */
    public function reset(): self;

    /**
     * Idempotent: repeated calls are no-ops without dispatching events.
     * Events are dispatched on the first pass only, even when there are no
     * services to finalize. reset() re-enables finalization.
     */
    public function finalize(): self;

    public function addFinalizer(string $class, callable $finalizer): self;

    /**
     * Does not invalidate the dependency tree cache: a factory intercepts
     * creation before resolution. Not applied to transient classes: the
     * transient branch of get() runs earlier.
     */
    /** @param (callable(\Duyler\DI\Container=): object) $factory */
    public function factory(string $className, callable $factory): self;

    /**
     * @return array<string>
     */
    public function compile(): array;

    /**
     * @param string|array<string> $tags
     */
    public function tag(string $serviceId, string|array $tags): self;

    /**
     * @return array<object>
     */
    public function tagged(string $tag): array;

    public function enableDebug(): self;

    public function disableDebug(): self;

    public function isDebugEnabled(): bool;

    public function getDebugInfo(): DebugInfo;

    /**
     * Appends the listener without deduplication: registering the same
     * listener twice invokes it twice per event. removeListeners clears
     * all listeners of an event; container reset() clears all listeners.
     */
    public function on(string $eventName, callable $listener): self;

    public function getEventDispatcher(): Event\EventDispatcher;

    /**
     * Decorators are applied when the service is first created; decorate()
     * after the first get() of a cached singleton takes effect only after
     * reset(); transient decorators are applied on every get(). A decorator
     * must return an object, otherwise ServiceNotCreatedException is thrown.
     *
     * @param class-string $serviceId
     */
    public function decorate(string $serviceId, callable $decorator): self;
}
