<?php

namespace App\Observers;

use App\Models\Module;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class ModuleObserver
{
    /**
     * Handle the Module "created" event.
     */
    public function created(Module $module): void
    {
        //
    }

    /**
     * Handle the Module "updated" event.
     */
    public function updated(Module $module): void
    {
        //
    }

    /**
     * Handle the Module "deleted" event.
     */
    public function deleted(Module $module): void
    {
        // 1. Hapus tabel dinamis dari database
        Schema::dropIfExists($module->table_name);

        $modelName = Str::studly(Str::singular($module->table_name));
        $snakeCaseModuleName = Str::snake($modelName);

        // 2. Hapus semua izin yang terkait
        Permission::where('name', 'like', "%_{$snakeCaseModuleName}")->delete();

        // 3. Hapus file model dinamis
        $modelPath = app_path('Models/Dynamic/'.$modelName.'.php');
        if (File::exists($modelPath)) {
            File::delete($modelPath);
        }

        // 4. Hapus file migrasi yang terkait
        $migrationFileNamePattern = '_create_'.$module->table_name.'_table.php';
        $migrationFiles = File::glob(database_path('migrations/*'.$migrationFileNamePattern));
        foreach ($migrationFiles as $migrationFile) {
            File::delete($migrationFile);
        }
    }

    /**
     * Handle the Module "restored" event.
     */
    public function restored(Module $module): void
    {
        //
    }

    /**
     * Handle the Module "force deleted" event.
     */
    public function forceDeleted(Module $module): void
    {
        //
    }
}
