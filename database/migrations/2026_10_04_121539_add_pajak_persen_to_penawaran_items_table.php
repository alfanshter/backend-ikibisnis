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
        Schema::table('penawaran_items', function (Blueprint $table) {
            $table->decimal('pajak_persen', 5, 2)->default(11.00)->after('pajak_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penawaran_items', function (Blueprint $table) {
            $table->dropColumn('pajak_persen');
        });
    }
};
