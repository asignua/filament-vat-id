<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId;

use Asignua\FilamentVatId\Support\RegistryManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VatIdServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-vat-id';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-vat-id.php and are read as
        // `__('filament-vat-id::filament-vat-id.<key>')`. Publish tag: `filament-vat-id-translations`.
        // Config: config/filament-vat-id.php (publish tag `filament-vat-id-config`).
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RegistryManager::class);
    }
}
