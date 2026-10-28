<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proyeks', function (Blueprint $table) {
            $table->string('tipe_kontrak')->default('Reguler')->after('divisi'); // 'Reguler' | 'Sewa'
            $table->date('sewa_mulai')->nullable()->after('tipe_kontrak');
            $table->date('sewa_selesai')->nullable()->after('sewa_mulai');
            $table->unsignedInteger('sewa_durasi_bulan')->nullable()->after('sewa_selesai');
            $table->string('skema_pembayaran')->nullable()->after('sewa_durasi_bulan'); // 'Bulanan' | 'Sekaligus' | 'Termin'
            $table->bigInteger('nominal_per_bulan')->nullable()->after('skema_pembayaran');
            $table->string('status_sewa')->default('Aktif')->nullable()->after('nominal_per_bulan'); // 'Aktif' | 'Mendekati Jatuh Tempo' | 'Kadaluarsa' | 'Selesai' | 'Diperpanjang'
        });

        Schema::create('proyek_sewa_perpanjangans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->constrained('proyeks')->cascadeOnDelete();
            $table->date('tanggal_perpanjangan');
            $table->date('sewa_mulai_lama')->nullable();
            $table->date('sewa_selesai_lama')->nullable();
            $table->date('sewa_mulai_baru');
            $table->date('sewa_selesai_baru');
            $table->unsignedInteger('durasi_bulan');
            $table->bigInteger('nominal')->default(0);
            $table->string('skema_pembayaran')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Update constraint on proyek_invoices.tipe if running PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE proyek_invoices DROP CONSTRAINT IF EXISTS proyek_invoices_tipe_check");
            DB::statement("ALTER TABLE proyek_invoices ADD CONSTRAINT proyek_invoices_tipe_check CHECK (((tipe)::text = ANY ((ARRAY['DP'::character varying, 'Termin'::character varying, 'Pelunasan'::character varying, 'Sewa'::character varying, 'Sewa Bulanan'::character varying, 'Lainnya'::character varying])::text[])))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE proyek_invoices DROP CONSTRAINT IF EXISTS proyek_invoices_tipe_check");
            DB::statement("ALTER TABLE proyek_invoices ADD CONSTRAINT proyek_invoices_tipe_check CHECK (((tipe)::text = ANY ((ARRAY['DP'::character varying, 'Termin'::character varying, 'Pelunasan'::character varying, 'Lainnya'::character varying])::text[])))");
        }

        Schema::dropIfExists('proyek_sewa_perpanjangans');

        Schema::table('proyeks', function (Blueprint $table) {
            $table->dropColumn([
                'tipe_kontrak',
                'sewa_mulai',
                'sewa_selesai',
                'sewa_durasi_bulan',
                'skema_pembayaran',
                'nominal_per_bulan',
                'status_sewa',
            ]);
        });
    }
};
