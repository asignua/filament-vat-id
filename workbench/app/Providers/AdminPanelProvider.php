<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Asignua\FilamentVatId\VatIdPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Filament\Resources\CompanyResource;
use Workbench\App\Support\ColouredAvatars;
use Workbench\App\Support\DemoRegistry;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        // Screenshots only: canned answers instead of the real registries, never in the test suite.
        if (filter_var(env('FILAMENT_VAT_ID_DEMO'), FILTER_VALIDATE_BOOLEAN)) {
            config(['filament-vat-id.registries' => [DemoRegistry::class]]);
        }
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Acme Supply')
            ->defaultAvatarProvider(ColouredAvatars::class)
            ->resources([CompanyResource::class])
            ->login()
            ->plugin(VatIdPlugin::make())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
