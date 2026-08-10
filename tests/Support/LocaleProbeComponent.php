<?php

declare(strict_types=1);

namespace Tests\Support;

use Livewire\Component;

/**
 * Test-only Livewire component for the storefront locale round-trip guard.
 *
 * Renders the *resolved* application locale so a test can prove whether the
 * locale survives a real POST /livewire/update, which carries no {locale} URL
 * prefix. See tests/Feature/Storefront/StorefrontLocaleLivewireTest.php.
 */
final class LocaleProbeComponent extends Component
{
    public int $count = 0;

    public function bump(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            <span id="locale">{{ app()->getLocale() }}</span>
            <span id="count">{{ $count }}</span>
            <span id="translated">{{ __('storefront_probe.greeting') }}</span>
            <button type="button" wire:click="bump">go</button>
        </div>
        BLADE;
    }
}
