<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyek_fee_tims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->constrained('proyeks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama');
            $table->enum('tipe', ['Karyawan Tetap', 'Freelance'])->default('Karyawan Tetap');
            $table->string('role')->nullable();
            $table->string('no_rekening')->nullable();
            $table->string('nama_bank')->nullable();
            $table->bigInteger('fee_total')->default(0);
            $table->bigInteger('fee_dibayar')->default(0);
            $table->enum('status_bayar', ['Belum Dibayar', 'Sebagian', 'Lunas'])->default('Belum Dibayar');
            $table->string('metode_bayar')->nullable();
            $table->date('tanggal_bayar')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('bukti_bayar')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proyek_fee_riwayats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_fee_tim_id')->constrained('proyek_fee_tims')->cascadeOnDelete();
            $table->date('tanggal');
            $table->bigInteger('nominal')->default(0);
            $table->string('metode')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('bukti')->nullable();
            $table->timestamps();
        });

        Schema::create('proyek_biaya_lains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->constrained('proyeks')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('nama');
            $table->enum('kategori', [
                'BBM / Transportasi', 'Konsumsi / Makan', 'Alat & Perkakas',
                'Bahan Pendukung', 'Pengiriman / Ekspedisi', 'Parkir & Tol',
                'Komunikasi', 'Lain-lain',
            ])->default('Lain-lain');
            $table->bigInteger('nominal')->default(0);
            $table->string('dibayar_oleh')->nullable();
            $table->enum('status', ['Belum Direimburse', 'Sudah Direimburse'])->default('Belum Direimburse');
            $table->text('keterangan')->nullable();
            $table->string('bukti')->nullable();
            $table->string('bukti_tipe')->nullable();  // 'image' | 'pdf'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proyek_procurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->constrained('proyeks')->cascadeOnDelete();
            $table->string('nama_item');
            $table->enum('kategori', ['Hardware', 'Kabel', 'Aksesoris', 'Tools', 'Lainnya'])->default('Lainnya');
            $table->decimal('qty', 10, 2)->default(1);
            $table->string('satuan')->default('pcs');
            $table->bigInteger('harga_satuan')->default(0);
            $table->string('supplier')->nullable();
            $table->date('tanggal_request');
            $table->date('tanggal_diterima')->nullable();
            $table->enum('status', ['Pending', 'Disetujui', 'Dipesan', 'Diterima', 'Dibatalkan'])->default('Pending');
            $table->text('catatan')->nullable();
            $table->string('request_by')->nullable();
            $table->string('bukti_transfer')->nullable();
            $table->string('bukti_diterima')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyek_procurements');
        Schema::dropIfExists('proyek_biaya_lains');
        Schema::dropIfExists('proyek_fee_riwayats');
        Schema::dropIfExists('proyek_fee_tims');
    }
};
