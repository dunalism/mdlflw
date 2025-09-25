<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ListDynamicEntries;
use App\Models\Module;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Pages\Auth\EditProfile;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Hasnayeen\Themes\Http\Middleware\SetTheme;
use Hasnayeen\Themes\ThemesPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Metode ini menambahkan item navigasi dinamis tanpa menghapus yang default.
     */
    public function boot(): void
    {
        Filament::serving(function () {
            $modules = Module::all();
            if ($modules->isNotEmpty()) {
                $dynamicItems = $modules
                    ->map(function ($module) {
                        $modelName = Str::studly(Str::singular($module->table_name));
                        $permissionName = 'page_'.Str::snake($modelName);

                        return NavigationItem::make($module->name)
                            ->url(ListDynamicEntries::getUrl(['module' => $module->table_name]))
                            ->icon($module->icon)
                            ->group('Dynamic Modules')
                            ->isActiveWhen(
                                fn (): bool => request()->routeIs('filament.admin.pages.list-dynamic-entries') &&
                                    request()->query('module') === $module->table_name,
                            )
                            /**
                             * =================================================================
                             * PERBAIKAN: Gunakan closure penuh dengan `use` dan Gate::allows()
                             * Ini menyelesaikan error 'Undefined variable' dan 'Undefined method'
                             * dengan cara yang paling kuat dan kompatibel.
                             * =================================================================
                             */
                            ->visible(function () use ($permissionName) {
                                return Gate::allows($permissionName);
                            });
                    })
                    ->all();
                Filament::registerNavigationItems($dynamicItems);
            }
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->sidebarCollapsibleOnDesktop()
            ->default()
            ->id('app')
            ->path('')
            ->login()
            ->profile(EditProfile::class)
            // ->passwordReset()

            /**
             * =================================================================
             * BAGIAN BRANDING APLIKASI
             * Di sinilah semua perubahan detail aplikasi dilakukan.
             * =================================================================
             */

            // 1. Mengubah Nama Aplikasi
            ->brandName('ModulFlow')

            // ->brandLogo(asset('images/modulflow-logo.png'))
            // ->brandLogoHeight('2.5rem')

            // 3. Mengatur Favicon (ikon di tab browser)
            ->favicon(asset('images/modulflow.ico'))

            ->emailVerification()
            ->userMenuItems([
                'profile' => MenuItem::make()->label('Edit profile'),
                // ...
            ])
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Pages\Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                SetTheme::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                ThemesPlugin::make()->canViewThemesPage(function () {
                    /** @var \App\Models\User $user */
                    $user = Auth::user();

                    return $user?->hasAnyRole('super_admin') ?? false;
                }),
            ])
            ->authMiddleware([Authenticate::class])
            ->spa()
            // Daftarkan Halaman Kustom kita di sini
            ->pages([ListDynamicEntries::class])

            ->renderHook(PanelsRenderHook::FOOTER, fn () => view('footer'));
    }
}
