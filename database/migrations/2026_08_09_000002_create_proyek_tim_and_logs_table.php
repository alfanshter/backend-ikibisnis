<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyek_tims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->constrained('proyeks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama');        // denormalized so freelance (no user) still works
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('log_pekerjaans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->constrained('proyeks')->cascadeOnDelete();
            $table->date('tanggal');
            $table->text('aktivitas');
            $table->json('tim')->nullable();   // array of names
            $table->string('durasi')->nullable();
            $table->text('kendala')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('log_pekerjaan_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_pekerjaan_id')->constrained('log_pekerjaans')->cascadeOnDelete();
            $table->text('url');
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_pekerjaan_fotos');
        Schema::dropIfExists('log_pekerjaans');
        Schema::dropIfExists('proyek_tims');
    }
};
