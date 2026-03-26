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
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->uuid('reviewed_by')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->uuid('investigated_by')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->uuid('closed_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {

        });
    }
};
