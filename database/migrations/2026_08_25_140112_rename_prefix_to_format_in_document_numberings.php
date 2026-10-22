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
        Schema::table('document_numberings', function (Blueprint $table) {
            $table->renameColumn('prefix', 'format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_numberings', function (Blueprint $table) {
            $table->renameColumn('format', 'prefix');
        });
    }
};
