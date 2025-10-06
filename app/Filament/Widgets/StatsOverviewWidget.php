<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $superAdminEmail = env('SUPER_ADMIN_EMAIL', 'superadmin@example.com');

        return [
            Stat::make('Total Users', User::count() + ($user->email === $superAdminEmail ? 0 : -1))
                ->description('All registered users')
                ->icon('heroicon-o-users')
                ->extraAttributes([
                    'x-data' => '{}',
                    'x-on:click' => "Livewire.navigate('/users')",
                    'style' => 'cursor: pointer;',
                ]),

            Stat::make('Total Modules', Module::count())
                ->description('All active modules')
                ->icon('heroicon-o-cube-transparent')
                ->extraAttributes([
                    'x-data' => '{}',
                    'x-on:click' => "Livewire.navigate('/modules')",
                    'style' => 'cursor: pointer;',
                ]),

            Stat::make('Total Roles', Role::count())
                ->description('Roles & permission groups')
                ->icon('heroicon-o-shield-check')
                ->extraAttributes(
                    $user->email === $superAdminEmail || $user->hasRole('super_admin')
                        ? [
                            'x-data' => '{}',
                            'x-on:click' => "Livewire.navigate('/shield/roles')",
                            'style' => 'cursor: pointer;',
                        ]
                        : [], // kalau bukan super admin → tidak ada atribut tambahan
                ),
        ];
    }

    /**
     * Tentukan siapa yang bisa melihat widget ini.
     */
    public static function canView(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->hasAnyRole(['super_admin', 'admin']);
    }
}
