<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survei_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survei_id')->constrained('surveis')->cascadeOnDelete();
            $table->string('name');
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survei_categories');
    }
};
