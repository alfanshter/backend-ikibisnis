<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('client');
            $table->string('perusahaan')->nullable();
            $table->string('surveyor')->nullable();
            $table->string('lokasi')->nullable();
            $table->date('tanggal');
            $table->time('waktu')->default('09:00:00');
            $table->text('kebutuhan')->nullable();
            $table->bigInteger('estimasi')->nullable()->default(0);
            $table->enum('status', ['Dijadwalkan', 'Selesai', 'Batal'])->default('Dijadwalkan');
            $table->text('catatan')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveis');
    }
};
