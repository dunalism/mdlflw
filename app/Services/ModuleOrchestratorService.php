<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ModuleOrchestratorService
{
    protected Module $module;

    protected string $tableName;

    protected string $modelName;

    public function __construct(Module $module)
    {
        $this->module = $module;
        $this->tableName = $module->table_name;
        // Contoh: 'products' -> 'Product'
        $this->modelName = Str::studly(Str::singular($this->tableName));
    }

    /**
     * Metode utama untuk menjalankan semua tugas secara berurutan.
     */
    public function execute(): void
    {
        $this->generateMigration();
        $this->runMigration();
        $this->generateModel();
        $this->generateAndAssignPermissions();
    }

    /**
     * TUGAS 1: Membuat file migrasi dari sebuah template (stub).
     */
    protected function generateMigration(): void
    {
        $stub = File::get(base_path('stubs/migration.create.stub'));

        // Ganti placeholder di template dengan data sebenarnya
        $stub = str_replace('{{tableName}}', $this->tableName, $stub);
        $stub = str_replace('{{columns}}', $this->buildColumnsSchema(), $stub);

        // Buat nama file migrasi yang unik dengan timestamp
        $migrationFileName = date('Y_m_d_His').'_create_'.$this->tableName.'_table.php';
        $migrationPath = database_path('migrations/'.$migrationFileName);

        File::put($migrationPath, $stub);
    }

    /**
     * TUGAS 2: Menjalankan perintah 'php artisan migrate'.
     */
    protected function runMigration(): void
    {
        Artisan::call('migrate');
    }

    /**
     * TUGAS 3: Membuat file Model Eloquent dari sebuah template (stub).
     */
    protected function generateModel(): void
    {
        $stub = File::get(base_path('stubs/model.stub'));

        // Ganti placeholder di template dengan data sebenarnya
        $stub = str_replace('{{namespace}}', 'App\Models\Dynamic', $stub);
        $stub = str_replace('{{className}}', $this->modelName, $stub);
        $stub = str_replace('{{tableName}}', $this->tableName, $stub);

        // Pastikan direktori 'app/Models/Dynamic' ada
        $modelPath = app_path('Models/Dynamic/');
        File::ensureDirectoryExists($modelPath);

        File::put($modelPath.$this->modelName.'.php', $stub);
        exec('composer dump-autoload');
    }

    /**
     * TUGAS 4: Membuat entri permission di database.
     */
    protected function generateAndAssignPermissions(): void
    {
        $resourceName = Str::snake($this->modelName);
        $permissions = [
            "page_{$resourceName}",
            "view_any_{$resourceName}",
            "view_{$resourceName}",
            "create_{$resourceName}",
            "update_{$resourceName}",
            "delete_{$resourceName}",
        ];

        $createdPermissions = [];
        foreach ($permissions as $permission) {
            // Buat izin dan kumpulkan
            $createdPermissions[] = Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }

        /**
         * =================================================================
         * 2. INI ADALAH LOGIKA BARUNYA
         * Secara otomatis berikan semua izin yang baru dibuat ke peran Admin.
         * =================================================================
         */
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo($createdPermissions);
            } else {
                Log::warning("Role '{$roleName}' not found during permission assignment.");
            }
        }
    }

    private const TYPE_MAP = [
        'string' => 'string',
        'text' => 'text',
        'wysiwyg' => 'text',
        'options' => 'string',
        'image' => 'string',
        'file' => 'string',
        'integer' => 'integer',
        'date' => 'date',
        'boolean' => 'boolean',
    ];

    /**
     * Helper untuk membangun string skema kolom untuk file migrasi.
     */
    private function buildColumnsSchema(): string
    {
        $schema = '';

        foreach ($this->module->fields as $field) {
            $type = self::TYPE_MAP[$field->data_type] ?? 'string'; // fallback ke string

            $line = "            \$table->{$type}('{$field->column_name}')";

            if (! $field->is_required) {
                $line .= '->nullable()';
            }

            if ($field->is_unique) {
                $line .= '->unique()';
            }

            $schema .= $line.";\n";
        }

        return $schema;
    }
}
