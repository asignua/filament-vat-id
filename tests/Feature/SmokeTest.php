<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Tests\Feature;

use Asignua\FilamentVatId\Tests\TestCase;
use Asignua\FilamentVatId\VatIdPlugin;
use Filament\Facades\Filament;

class SmokeTest extends TestCase
{
    public function test_the_panel_boots(): void
    {
        $this->assertSame('admin', Filament::getCurrentPanel()?->getId());
    }

    public function test_the_plugin_is_registered_on_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('asignua-filament-vat-id'));
        $this->assertInstanceOf(VatIdPlugin::class, $panel->getPlugin('asignua-filament-vat-id'));
    }

    public function test_the_translations_are_loaded(): void
    {
        $this->assertSame('Look up', __('filament-vat-id::filament-vat-id.lookup.action'));
    }
}
