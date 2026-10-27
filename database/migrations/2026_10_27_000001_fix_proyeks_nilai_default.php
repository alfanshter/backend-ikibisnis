<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix: kolom 'nilai' pada tabel proyeks tidak memiliki default value di PostgreSQL VPS,
     * menyebabkan NOT NULL violation saat create proyek tanpa mengisi nilai kontrak.
     */
    public function up(): void
    {
        // Update semua baris yang nilai-nya NULL (jika ada) ke 0
        DB::table('proyeks')->whereNull('nilai')->update(['nilai' => 0]);

        // Set default value 0 pada kolom di level database
        Schema::table('proyeks', function (Blueprint $table) {
            $table->bigInteger('nilai')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('proyeks', function (Blueprint $table) {
            $table->bigInteger('nilai')->default(null)->change();
        });
    }
};
