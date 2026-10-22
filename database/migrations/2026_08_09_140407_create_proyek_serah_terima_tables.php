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
        Schema::create('proyek_serah_terimas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->unique()->constrained('proyeks')->cascadeOnDelete();
            $table->string('nomor_bast')->unique()->nullable();
            $table->date('tanggal')->nullable();
            $table->time('waktu')->nullable();
            $table->foreignId('pic_pws_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pic_client_nama')->nullable();
            $table->string('pic_client_jabatan')->nullable();
            $table->enum('status', ['Draft', 'Menunggu Tanda Tangan', 'Diterima', 'Diterima dengan Catatan', 'Revisi', 'Selesai'])->default('Draft');
            $table->text('hasil_pekerjaan')->nullable();
            $table->text('catatan_serah_terima')->nullable();
            $table->string('ttd_pws')->nullable();
            $table->string('ttd_client')->nullable();
            $table->dateTime('ttd_pws_tgl')->nullable();
            $table->dateTime('ttd_client_tgl')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proyek_st_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serah_terima_id')->constrained('proyek_serah_terimas')->cascadeOnDelete();
            $table->string('text');
            $table->enum('status', ['OK', 'OK dengan Catatan', 'Tidak OK'])->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('proyek_st_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serah_terima_id')->constrained('proyek_serah_terimas')->cascadeOnDelete();
            $table->string('nama_item');
            $table->integer('qty')->default(1);
            $table->string('unit')->default('Unit');
            $table->string('sn')->nullable();
            $table->enum('kondisi', ['Baik', 'Baik dengan Catatan', 'Rusak', 'Tidak Ada'])->default('Baik');
            $table->text('catatan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('proyek_st_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serah_terima_id')->constrained('proyek_serah_terimas')->cascadeOnDelete();
            $table->string('nama_dokumen');
            $table->string('file_url');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proyek_st_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serah_terima_id')->constrained('proyek_serah_terimas')->cascadeOnDelete();
            $table->enum('kategori', ['Sebelum Pekerjaan', 'Saat Pekerjaan', 'Setelah Pekerjaan']);
            $table->string('file_url');
            $table->string('caption')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proyek_st_punch_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serah_terima_id')->constrained('proyek_serah_terimas')->cascadeOnDelete();
            $table->text('temuan');
            $table->string('pic')->nullable();
            $table->date('deadline')->nullable();
            $table->enum('status', ['Open', 'In Progress', 'Resolved', 'Closed'])->default('Open');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyek_st_punch_lists');
        Schema::dropIfExists('proyek_st_photos');
        Schema::dropIfExists('proyek_st_documents');
        Schema::dropIfExists('proyek_st_equipments');
        Schema::dropIfExists('proyek_st_checklists');
        Schema::dropIfExists('proyek_serah_terimas');
    }
};
