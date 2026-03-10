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
            $table->string('incident_number')->nullable()->after('incident_at');
            $table->string('other_incident_type')->nullable()->after('incident_type');
            $table->text('how_incident_picked')->nullable();
            $table->date('date_insurance_claim_submitted')->nullable();
            $table->decimal('amount_recovered', 14, 2)->nullable()->after('financial_loss');
            $table->decimal('amount_unrecovered', 14, 2)->nullable()->after('financial_loss');
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
