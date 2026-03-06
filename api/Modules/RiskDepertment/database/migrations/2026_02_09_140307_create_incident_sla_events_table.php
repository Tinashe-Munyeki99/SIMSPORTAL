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
        Schema::create('incident_sla_events', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('site_id', 36)->index();
            $table->char('incident_report_id', 36)->index();

            // late_log | close_overdue
            $table->string('event_type', 50)->index();

            // minutes breached (late by / overdue by)
            $table->integer('minutes_value')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['site_id','incident_report_id','event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_sla_events');
    }
};
