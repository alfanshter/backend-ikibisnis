<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyek_qcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->unique()->constrained('proyeks')->cascadeOnDelete();
            $table->string('qc_pic')->nullable();
            $table->date('qc_tanggal')->nullable();
            $table->enum('status', ['Draft', 'Dalam Review', 'Lulus', 'Revisi'])->default('Draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proyek_qc_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_qc_id')->constrained('proyek_qcs')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('proyek_qc_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_qc_section_id')->constrained('proyek_qc_sections')->cascadeOnDelete();
            $table->text('text');
            $table->boolean('checked')->default(false);
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyek_qc_items');
        Schema::dropIfExists('proyek_qc_sections');
        Schema::dropIfExists('proyek_qcs');
    }
};
