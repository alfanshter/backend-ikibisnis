<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyeks', function (Blueprint $table) {
            $table->string('status_pembayaran')->default('Belum Dibayar')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('proyeks', function (Blueprint $table) {
            $table->dropColumn('status_pembayaran');
        });
    }
};
