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
        Schema::table('proyeks', function (Blueprint $table) {
            $table->string('akses_tipe')->default('personal')->after('status');
            $table->json('allowed_roles')->nullable()->after('akses_tipe');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('akses_tipe')->default('semua')->after('status');
            $table->json('allowed_roles')->nullable()->after('akses_tipe');
            $table->json('assigned_users')->nullable()->after('allowed_roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proyeks', function (Blueprint $table) {
            $table->dropColumn(['akses_tipe', 'allowed_roles']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['akses_tipe', 'allowed_roles', 'assigned_users']);
        });
    }
};
