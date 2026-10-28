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
        Schema::table('leads', function (Blueprint $table) {
            $table->string('tipe_kontrak')->default('Langsung')->after('tipe_proyek'); // 'Langsung' | 'Sewa'
            $table->unsignedInteger('sewa_durasi_bulan')->nullable()->default(12)->after('tipe_kontrak');
            $table->string('skema_pembayaran')->nullable()->default('Bulanan')->after('sewa_durasi_bulan'); // 'Bulanan' | 'Sekaligus'
            $table->date('sewa_mulai')->nullable()->after('skema_pembayaran');
            $table->date('sewa_selesai')->nullable()->after('sewa_mulai');
            $table->bigInteger('nominal_per_bulan')->nullable()->after('sewa_selesai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'tipe_kontrak',
                'sewa_durasi_bulan',
                'skema_pembayaran',
                'sewa_mulai',
                'sewa_selesai',
                'nominal_per_bulan',
            ]);
        });
    }
};
