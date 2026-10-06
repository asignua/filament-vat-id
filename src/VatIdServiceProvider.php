<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VatIdServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-vat-id';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-vat-id.php and are read as
        // `__('filament-vat-id::filament-vat-id.<key>')`. Publish tag: `filament-vat-id-translations`.
        $package->name(static::$name)
            ->hasTranslations()
            ->hasViews();

        // Add a config file only when the plugin really has options: create config/filament-vat-id.php and
        // chain `->hasConfigFile()` here (publish tag `filament-vat-id-config`). Prefer fluent setters on the Plugin.
    }
}
