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
        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            // Type: aset_lancar | aset_tetap | liabilitas_pendek | liabilitas_panjang | ekuitas
            $table->string('type');
            $table->string('label');
            $table->bigInteger('amount'); // can be negative (e.g. accumulated depreciation)
            $table->date('date');         // effective date (for balance sheet point-in-time)
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
    }
};
