<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ModulFlowGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:modulflow-generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fresh migrate + seed, setup super-admin dan generate Shield';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1) Migrate fresh + seed
        $this->info('Running migrate:fresh --seed…');
        $exit = $this->call('migrate:fresh', [
            '--seed' => true,
        ]);
        if ($exit !== 0) {
            $this->error('Artisan migrate:fresh gagal dengan code: '.$exit);

            return $exit;
        }

        // 2) Buat super-admin user=11
        $this->info('Running shield:super-admin for user=11…');
        $exit = $this->call('shield:super-admin', [
            '--user' => 11,
            '--panel' => 'app',
        ]);
        if ($exit !== 0) {
            $this->error('Artisan shield:super-admin gagal dengan code: '.$exit);

            return $exit;
        }

        // 3) Generate semua policy/permissions
        $this->info('Running shield:generate --all…');
        $exit = $this->call('shield:generate', [
            '--all' => true,
            '--ignore-existing-policies' => true,
            '--ignore-config-exclude' => true,
            '--panel' => 'app',
        ]);
        if ($exit !== 0) {
            $this->error('Artisan shield:generate gagal dengan code: '.$exit);

            return $exit;
        }

        $this->info('✅ Semua perintah berhasil dijalankan.');

        return 0;
    }
}
