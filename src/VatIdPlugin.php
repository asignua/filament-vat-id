<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId;

use Asignua\FilamentVatId\Contracts\CompanyRegistry;
use Asignua\FilamentVatId\Support\RegistryManager;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * The fields and rules work without registering the plugin. Registering it only gives a place to plug in extra
 * registries from the panel provider:
 *
 *     ->plugin(VatIdPlugin::make()->registry(new MyUkrainianRegistry))
 */
class VatIdPlugin implements Plugin
{
    /** @var list<class-string<CompanyRegistry>|CompanyRegistry> */
    protected array $registries = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'asignua-filament-vat-id';
    }

    /**
     * Adds a registry after the configured ones.
     *
     * @param class-string<CompanyRegistry>|CompanyRegistry $registry
     */
    public function registry(CompanyRegistry|string $registry): static
    {
        $this->registries[] = $registry;

        return $this;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void
    {
        $manager = app(RegistryManager::class);

        foreach ($this->registries as $registry) {
            $manager->extend($registry);
        }
    }
}
