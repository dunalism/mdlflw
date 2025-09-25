<?php

namespace App\Filament\Traits;

trait HandlesDynamicStatusDisplay
{
    /**
     * Memeriksa apakah nama kolom kemungkinan besar adalah kolom status.
     */
    protected function isStatusColumn(string $columnName): bool
    {
        // Daftar kata kunci yang menandakan kolom status (dalam huruf kecil)
        $statusKeywords = [
            'status',
            'state',
            'kondisi',
            'condition',
            'statuses',
            'states',
            'conditions',
            'presence',
            'kehadiran',
            'ketersediaan',
            'availability',
        ];

        return in_array(strtolower($columnName), $statusKeywords);
    }

    /**
     * Menerjemahkan nilai status menjadi warna lencana Filament.
     * Mendukung bahasa Inggris dan Indonesia.
     */
    protected function getStatusColor(string $statusValue): ?string
    {
        $lowerStatus = strtolower(trim($statusValue));

        $colorMap = [
            'success' => [
                'active',
                'aktif',
                'completed',
                'selesai',
                'success',
                'sukses',
                'published',
                'diterbitkan',
                'approved',
                'disetujui',
                'paid',
                'lunas',
                'done',
                'available',
                'ready',
                'tersedia',
                'lunas',
                'dibaca',
                'sudah dibaca',
                'read',
            ],
            'warning' => [
                'pending',
                'menunggu',
                'in progress',
                'sedang diproses',
                'review',
                'ditinjau',
                'processing',
                'diproses',
                'sedang dibaca',
            ],
            'danger' => [
                'inactive',
                'tidak aktif',
                'failed',
                'gagal',
                'rejected',
                'ditolak',
                'cancelled',
                'dibatalkan',
                'overdue',
                'terlambat',
                'expired',
                'kadaluarsa',
                'unpaid',
                'belum lunas',
                'unavailable',
                'tidak tersedia',
                'unread',
                'belum dibaca',
            ],
            'gray' => ['draft', 'draf', 'archived', 'diarsipkan', 'on hold', 'ditunda'],
        ];

        foreach ($colorMap as $color => $keywords) {
            if (in_array($lowerStatus, $keywords)) {
                return $color;
            }
        }

        // Kembalikan null jika tidak ada yang cocok, agar bisa menggunakan warna default.
        return null;
    }
}
