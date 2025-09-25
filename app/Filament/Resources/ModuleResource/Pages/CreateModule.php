<?php

namespace App\Filament\Resources\ModuleResource\Pages;

use App\Filament\Resources\ModuleResource;
use App\Services\ModuleOrchestratorService; // <-- 1. Import Service Anda
use Filament\Resources\Pages\CreateRecord;

class CreateModule extends CreateRecord
{
    protected static string $resource = ModuleResource::class;

    /**
     * Metode ini akan berjalan SETELAH record berhasil dibuat
     * dan semua relasinya (dari Repeater) berhasil disimpan.
     */
    protected function afterCreate(): void
    {
        // $this->record berisi model Module yang baru saja dibuat,
        // lengkap dengan relasi 'fields' yang sudah terisi.

        $service = new ModuleOrchestratorService($this->record);
        $service->execute();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
