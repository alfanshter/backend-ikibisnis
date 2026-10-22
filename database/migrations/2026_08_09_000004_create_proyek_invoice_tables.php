<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyek_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->constrained('proyeks')->cascadeOnDelete();
            $table->string('nomor_invoice')->unique();
            $table->date('tanggal');
            $table->date('jatuh_tempo')->nullable();
            $table->string('perihal')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedTinyInteger('ppn')->default(11); // percent
            $table->enum('tipe', ['DP', 'Termin', 'Pelunasan', 'Lainnya'])->default('Lainnya');
            $table->unsignedTinyInteger('nomor_termin')->nullable();
            $table->enum('status', ['Draft', 'Terkirim', 'Lunas', 'Jatuh Tempo'])->default('Draft');
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('total_harga')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proyek_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_invoice_id')->constrained('proyek_invoices')->cascadeOnDelete();
            $table->string('nama');
            $table->string('satuan')->default('unit');
            $table->decimal('qty', 10, 2)->default(1);
            $table->bigInteger('harga_satuan')->default(0);
            $table->unsignedTinyInteger('diskon')->default(0); // percent
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyek_invoice_items');
        Schema::dropIfExists('proyek_invoices');
    }
};
