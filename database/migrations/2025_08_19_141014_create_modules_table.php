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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Nama modul yang ramah pengguna, cth: Produk');
            $table->string('table_name')->unique()->comment('Nama tabel fisik di database, cth: products');
            $table->string('icon')->default('heroicon-o-cube')->comment('Ikon Heroicon untuk navigasi UI');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
