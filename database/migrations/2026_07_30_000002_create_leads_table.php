<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('nama');
            $table->string('perusahaan');
            $table->string('pic')->nullable();
            $table->text('kebutuhan')->nullable();
            $table->enum('sumber', ['WhatsApp', 'Referensi', 'Website', 'Instagram', 'Langsung', 'Telepon'])->default('WhatsApp');
            $table->bigInteger('estimasi')->nullable();
            $table->enum('status', ['Prospek', 'Survey', 'Penawaran', 'Deal', 'Pending', 'Batal'])->default('Prospek');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('lokasi')->nullable();
            $table->text('catatan')->nullable();       // internal notes
            // Lingkup proyek
            $table->string('tipe_proyek')->nullable();
            $table->string('skala')->nullable();
            $table->string('intensitas')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
