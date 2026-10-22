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
            if (!Schema::hasColumn('penawaran_items', 'jenis_item')) {
                $table->string('jenis_item')->default('barang')->after('part_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penawaran_items', function (Blueprint $table) {
            $table->dropColumn('jenis_item');
        });
    }
};
