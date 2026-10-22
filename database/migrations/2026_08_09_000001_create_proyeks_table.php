<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyeks', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('client_nama')->nullable();
            $table->string('perusahaan')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('kategori')->nullable();
            $table->string('divisi')->nullable();
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal')->nullable();
            $table->date('target_selesai')->nullable();
            $table->text('deskripsi')->nullable();
            $table->bigInteger('nilai')->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->enum('status', [
                'Belum Dimulai', 'Berjalan', 'QC', 'Serah Terima', 'Selesai', 'Hold'
            ])->default('Belum Dimulai');
            $table->foreignId('penawaran_id')->nullable()->constrained('penawarans')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyeks');
    }
};
