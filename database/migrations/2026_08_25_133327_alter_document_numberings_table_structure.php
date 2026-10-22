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
            $table->dropColumn(['format', 'current_sequence']);
            $table->string('prefix')->nullable()->after('module_label');
            $table->integer('starting_number')->default(1)->after('prefix');
            $table->integer('digit')->default(4)->after('starting_number');
            $table->integer('current_number')->default(0)->after('digit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_numberings', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'starting_number', 'digit', 'current_number']);
            $table->string('format')->after('module_label')->nullable();
            $table->integer('current_sequence')->default(0)->after('format');
        });
    }
};
