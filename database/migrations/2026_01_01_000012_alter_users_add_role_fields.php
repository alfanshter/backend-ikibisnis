<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->after('password');
            $table->string('avatar_color', 20)->default('#6366f1')->after('status');
            $table->timestamp('last_login_at')->nullable()->after('avatar_color');
            $table->foreignId('created_by')->nullable()->after('last_login_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['status', 'avatar_color', 'last_login_at', 'created_by']);
        });
    }
};
