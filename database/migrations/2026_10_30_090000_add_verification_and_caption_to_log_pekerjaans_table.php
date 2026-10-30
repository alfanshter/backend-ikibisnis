<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_pekerjaans', function (Blueprint $table) {
            $table->string('status_verifikasi', 50)->default('Menunggu Review')->after('kendala');
            $table->text('catatan_revisi')->nullable()->after('status_verifikasi');
            $table->foreignId('verified_by')->nullable()->after('catatan_revisi')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });

        Schema::table('log_pekerjaan_fotos', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('log_pekerjaan_fotos', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });

        Schema::table('log_pekerjaans', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['status_verifikasi', 'catatan_revisi', 'verified_by', 'verified_at']);
        });
    }
};

