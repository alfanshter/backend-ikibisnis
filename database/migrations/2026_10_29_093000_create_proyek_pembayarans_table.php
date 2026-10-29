<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyek_pembayarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyek_id')->constrained('proyeks')->cascadeOnDelete();
            $table->foreignId('proyek_invoice_id')->nullable()->constrained('proyek_invoices')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('nomor_pembayaran')->nullable();
            $table->unsignedSmallInteger('bulan_ke')->nullable();
            $table->string('periode_bulan')->nullable();
            $table->date('tanggal_bayar');
            $table->bigInteger('nominal')->default(0);
            $table->string('metode_pembayaran')->default('Transfer Bank');
            $table->text('catatan')->nullable();
            $table->string('bukti_bayar')->nullable();
            $table->boolean('masuk_pembukuan')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyek_pembayarans');
    }
};
