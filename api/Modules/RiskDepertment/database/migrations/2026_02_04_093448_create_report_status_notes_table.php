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
        Schema::create('report_status_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('incident_report_id');
            $table->uuid('user_id');
            $table->enum('status', [
                'under_review',
                'investigating',
                'resolved',
                'closed',
                'rejected'
            ])->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_status_notes');
    }
};
