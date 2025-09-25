<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('module_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->string('column_name')->comment('Nama kolom di tabel database');
            $table->string('data_type')->comment('Tipe data (String, Integer, Text, Date, dll.)');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->text('options')->nullable();
            $table->string('source_type')->default('manual');
            // Menyimpan ID dari modul terkait jika source_type adalah 'module'
            $table->foreignId('related_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->timestamps();

            // Memastikan kombinasi module_id dan column_name adalah unik
            $table->unique(['module_id', 'column_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_fields');
    }
};
