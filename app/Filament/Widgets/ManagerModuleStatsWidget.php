<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagerModuleStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $stats = [];

        // Ambil semua modul dan periksa izin 'page' untuk masing-masing.
        $modules = Module::all();
        foreach ($modules as $module) {
            $permissionName = 'page_'.Str::singular($module->table_name);

            if ($user->can($permissionName)) {
                // Jika diizinkan, hitung jumlah record di tabel dinamis.
                $count = DB::table($module->table_name)->count();
                $stats[] = Stat::make('Total '.$module->name, $count)
                    ->icon($module->icon)
                    ->extraAttributes([
                        'x-data' => '{}',
                        'x-on:click' => "Livewire.navigate('/list-dynamic-entries?module={$module->table_name}')",
                        'style' => 'cursor: pointer;',
                    ]);
            }
        }

        return $stats;
    }

    public static function canView(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userIsManager = $user->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'manager') || str_contains($roleName, 'manajer');
        });

        $userIsStaff = $user->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'staff') ||
                str_contains($roleName, 'staf') ||
                str_contains($roleName, 'editor') ||
                str_contains($roleName, 'karyawan') ||
                str_contains($roleName, 'employee');
        });

        return $userIsManager || $userIsStaff;
    }
}
