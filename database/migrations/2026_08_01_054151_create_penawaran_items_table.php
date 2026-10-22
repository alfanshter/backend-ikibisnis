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
        Schema::create('penawaran_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penawaran_id')->constrained()->onDelete('cascade');
            $table->foreignId('part_id')->nullable()->constrained()->onDelete('set null');
            $table->string('nama_item');
            $table->text('deskripsi')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('total_harga', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penawaran_items');
    }
};
