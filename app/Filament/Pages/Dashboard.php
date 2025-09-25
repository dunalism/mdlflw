<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ManagerModuleStatsWidget;
use App\Filament\Widgets\MyRecentActivityWidget;
use App\Filament\Widgets\RecentAuditsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Pages\Dashboard as BasePage;

class Dashboard extends BasePage
{
    public function getWidgets(): array
    {
        return [
            // Widget yang akan dicoba untuk ditampilkan ke semua orang.
            // Filament akan secara otomatis menyaringnya berdasarkan metode canView() di setiap widget.
            StatsOverviewWidget::class,
            ManagerModuleStatsWidget::class,
            RecentAuditsWidget::class,
            MyRecentActivityWidget::class, // <-- 2. Tambahkan widget baru di sini
        ];
    }
}
