<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_users', function (Blueprint $table) {
            if (!Schema::hasColumn('system_users', 'password_setup_token')) {
                $table->string('password_setup_token', 128)->nullable()->after('password');
            }

            if (!Schema::hasColumn('system_users', 'password_setup_expires_at')) {
                $table->timestamp('password_setup_expires_at')->nullable()->after('password_setup_token');
            }

            if (!Schema::hasColumn('system_users', 'password_setup_completed_at')) {
                $table->timestamp('password_setup_completed_at')->nullable()->after('password_setup_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_users', function (Blueprint $table) {
            if (Schema::hasColumn('system_users', 'password_setup_completed_at')) {
                $table->dropColumn('password_setup_completed_at');
            }

            if (Schema::hasColumn('system_users', 'password_setup_expires_at')) {
                $table->dropColumn('password_setup_expires_at');
            }

            if (Schema::hasColumn('system_users', 'password_setup_token')) {
                $table->dropColumn('password_setup_token');
            }
        });
    }
};
