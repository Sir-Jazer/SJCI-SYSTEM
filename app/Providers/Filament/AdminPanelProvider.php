<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ForcePasswordChange;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Uses the church logo once it is saved to public/images/sjci-logo.png;
        // until then the brand name is shown instead of a broken image.
        // NOTE: the committed file is SJCI-LOGO.png (uppercase). Must match exactly —
        // Linux hosts (Render/Oracle) are case-sensitive even though macOS isn't.
        $logoExists = file_exists(public_path('images/SJCI-LOGO.png'));

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Invite-only: no ->registration(). Accounts are created by the Head
            // Pastor. Pastors can manage their own profile and recover a lost
            // password by email (uses the configured mailer; falls back to the
            // log mailer in local dev).
            ->profile(isSimple: false)
            ->passwordReset()
            ->brandName('Shepherd Jubillee Church Inc.')
            ->brandLogo($logoExists ? fn () => view('filament.logo') : null)
            ->favicon($logoExists ? asset('images/SJCI-LOGO.png') : null)
            ->colors([
                'primary' => Color::Blue, // church blue — buttons, links, active nav (well-tuned scale)
                'info' => Color::hex('#0ea5e9'),
                'success' => Color::hex('#16a34a'), // fund green
                'warning' => Color::hex('#eab308'), // logo yellow
                'danger' => Color::hex('#dc2626'),
                'gray' => Color::Slate, // cool neutral to match the navy
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.branding')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                ForcePasswordChange::class,
            ]);
    }
}
