<?php

namespace App\Observers;

use App\Models\ModuleField;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ModuleFieldObserver
{
    /**
     * Menangani event "created" pada ModuleField.
     */
    public function created(ModuleField $field): void
    {
        $tableName = $field->module->table_name;
        $dbType = $this->getDbType($field->data_type);

        if (! Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($field, $dbType) {
                $column = $table->{$dbType}($field->column_name);
                if (! $field->is_required) {
                    $column->nullable();
                }
                if ($field->is_unique) {
                    $column->unique();
                }
            });
        } catch (QueryException $e) {
            Log::error("Gagal menambahkan kolom '{$field->column_name}' ke tabel '{$tableName}': ".$e->getMessage());
            $field->deleteQuietly(); // Rollback
        }
    }

    /**
     * Menangani event "updated" pada ModuleField.
     * Logika ini dirombak total untuk menangani semua kasus dengan urutan yang benar.
     */
    public function updated(ModuleField $field): void
    {
        $tableName = $field->module->table_name;
        if (! Schema::hasTable($tableName)) {
            return;
        }

        try {
            DB::transaction(function () use ($field, $tableName) {
                $originalColumnName = $field->getOriginal('column_name');
                $newColumnName = $field->column_name;
                $dbType = $this->getDbType($field->data_type);

                // Langkah 1: Hapus indeks unik lama (jika ada) pada NAMA LAMA.
                if ($field->getOriginal('is_unique')) {
                    Schema::table($tableName, function (Blueprint $table) use ($originalColumnName, $tableName) {
                        $table->dropUnique("{$tableName}_{$originalColumnName}_unique");
                    });
                }

                // Langkah 2: Ubah properti kolom (tipe data, nullable) pada NAMA LAMA.
                if ($field->isDirty('data_type', 'is_required')) {
                    Schema::table($tableName, function (Blueprint $table) use ($field, $originalColumnName, $dbType) {
                        $column = $table->{$dbType}($originalColumnName);
                        if (! $field->is_required) {
                            $column->nullable();
                        }
                        $column->change();
                    });
                }

                // Langkah 3: Ganti nama kolom (jika berubah).
                if ($field->isDirty('column_name') && Schema::hasColumn($tableName, $originalColumnName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($originalColumnName, $newColumnName) {
                        $table->renameColumn($originalColumnName, $newColumnName);
                    });
                }

                // Langkah 4: Tambahkan indeks unik baru (jika diperlukan) pada NAMA BARU.
                if ($field->is_unique) {
                    Schema::table($tableName, function (Blueprint $table) use ($newColumnName) {
                        $table->unique($newColumnName);
                    });
                }
            });
        } catch (QueryException $e) {
            Log::error("Gagal memperbarui kolom di tabel '{$tableName}': ".$e->getMessage());
            $field->setRawAttributes($field->getOriginal())->saveQuietly(); // Rollback
        }
    }

    /**
     * Menangani event "deleted" pada ModuleField.
     */
    public function deleted(ModuleField $field): void
    {
        $tableName = $field->module->table_name;
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $field->column_name)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($field, $tableName) {
                if ($field->is_unique) {
                    $table->dropUnique("{$tableName}_{$field->column_name}_unique");
                }
                $table->dropColumn($field->column_name);
            });
        } catch (QueryException $e) {
            Log::error("Gagal menghapus kolom '{$field->column_name}' dari tabel '{$tableName}': ".$e->getMessage());
        }
    }

    // --- Helper Method ---
    protected function getDbType(string $dataType): string
    {
        return match ($dataType) {
            'wysiwyg', 'text' => 'text',
            'options', 'image', 'file' => 'string',
            default => $dataType,
        };
    }
}
