<?php

declare(strict_types=1);

/**
 * Compat shim for the pestphp/pest-plugin-livewire helper `livewire()`.
 *
 * The plugin is not installed (Phase 1 package-list discipline). This file
 * provides:
 *  1. A `LivewireTestable` proxy that:
 *     - defers construction of the underlying Livewire Testable until first
 *       non-actingAs method call, so `actingAs($user)` can set auth state
 *       BEFORE the component's canViewAny() / canView() gates run (Filament
 *       resource gates evaluate at mount time).
 *     - forces fluent chaining even for methods that return void/null
 *       (Livewire 3's Testable::actingAs is a static void method).
 *  2. The `Pest\Livewire\livewire()` namespaced function so existing
 *     `use function Pest\Livewire\livewire;` test imports keep working.
 */

namespace Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * @mixin Testable
 */
final class LivewireTestable
{
    private ?Testable $inner = null;

    /**
     * @param  class-string|string  $name
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        private readonly string $name,
        private readonly array $params = [],
    ) {}

    public function actingAs(Authenticatable $user, ?string $driver = null): self
    {
        if (isset($user->wasRecentlyCreated) && $user->wasRecentlyCreated) {
            $user->wasRecentlyCreated = false;
        }
        auth()->guard($driver)->setUser($user);
        if ($driver !== null) {
            auth()->shouldUse($driver);
        }

        // Force re-construction so Filament gates re-evaluate with the new user.
        $this->inner = null;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        if ($this->inner === null) {
            $this->inner = Livewire::test($this->name, $this->params);
        }

        $result = $this->inner->{$method}(...$args);

        if ($result === null) {
            return $this;
        }

        if ($result instanceof Testable) {
            $this->inner = $result;

            return $this;
        }

        return $result;
    }
}

namespace Pest\Livewire;

use Tests\Support\LivewireTestable;

if (! function_exists(__NAMESPACE__.'\\livewire')) {
    /**
     * @param  class-string|string  $name
     * @param  array<string, mixed>  $params
     */
    function livewire(string $name, array $params = []): LivewireTestable
    {
        return new LivewireTestable($name, $params);
    }
}
