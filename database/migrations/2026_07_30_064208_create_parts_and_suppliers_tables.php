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
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('sku')->unique();
            $table->string('kategori');
            $table->string('merek')->nullable();
            $table->text('spesifikasi')->nullable();
            $table->string('satuan')->default('pcs');
            $table->integer('stok_min')->default(1);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('part_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->onDelete('cascade');
            $table->string('nama');
            $table->string('kontak')->nullable();
            $table->decimal('harga', 15, 2)->default(0);
            $table->string('lead_time')->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part_suppliers');
        Schema::dropIfExists('parts');
    }
};
