<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Console\Command;

class AuditCleanRememberToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:clean-remember-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove useless audit logs for user remember_token updates.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Mencari log audit remember_token yang tidak perlu...');

        // Cari log di mana:
        // 1. Modelnya adalah User
        // 2. Aksi-nya adalah 'updated'
        // 3. Hanya ada satu atribut yang berubah
        // 4. Atribut yang berubah itu adalah 'remember_token'
        $query = Audit::where('auditable_type', User::class)
            ->where('event', 'updated')
            ->whereJsonLength('old_values', 1)
            ->whereJsonContainsKey('old_values', 'remember_token');

        $count = $query->count();

        if ($count === 0) {
            $this->info('Tidak ada log yang perlu dibersihkan. Database Anda sudah bersih!');

            return;
        }

        if ($this->confirm("Ditemukan {$count} log yang akan dihapus. Apakah Anda ingin melanjutkan?", true)) {
            $deletedCount = $query->delete();
            $this->info("Berhasil menghapus {$deletedCount} log audit.");
        } else {
            $this->info('Operasi dibatalkan.');
        }
    }
}
