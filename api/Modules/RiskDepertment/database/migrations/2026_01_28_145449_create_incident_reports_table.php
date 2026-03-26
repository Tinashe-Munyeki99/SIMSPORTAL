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
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Multi-tenant / site scoping (matches your app('site') pattern)
            $table->uuid('site_id')->index();
            $table->uuid('department_id')->nullable();

            // Org metadata
            $table->string('division')->nullable();      // e.g. NR-DAD, SR-DAD
            $table->string('location')->nullable();      // e.g. Borrowdale Road
            $table->dateTime('incident_at')->index();    // date & time of incident

            // People / parties (store names; optionally link to users if you want later)
            $table->string('reported_by')->nullable();    // reporter name
            $table->string('accused')->nullable();        // accused / involved person

            // Incident content
            $table->string('incident_type')->index();     // e.g. RTA, Fraud, Theft, Safety
            $table->text('incident_summary')->nullable(); // what happened (narrative)
            $table->text('root_cause')->nullable();       // cause analysis
            $table->text('impact')->nullable();           // operational impact/injuries etc.
            $table->text('immediate_action')->nullable(); // initial response taken
            $table->text('corrective_action')->nullable();// fix applied
            $table->text('preventive_action')->nullable();// measures to stop repeat

            // Loss / financials
            $table->boolean('loss_still_happening')->default(false);
            $table->decimal('financial_loss', 14, 2)->nullable(); // 999999999999.99
            $table->string('currency', 10)->default('USD');

            // Police / legal
            $table->boolean('police_required')->default(false);
            $table->boolean('police_reported')->default(false);
            $table->string('police_station')->nullable();
            $table->string('police_case_number')->nullable();
            $table->text('police_action_plan')->nullable();

            // Workflow / status
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'investigating',
                'resolved',
                'closed',
                'rejected'
            ])->default('draft')->index();

            $table->enum('severity', ['low', 'medium', 'high', 'critical'])
                ->default('low')->index();

            // Accountability
            $table->string('respondent')->nullable();     // person who responded
            $table->text('management_comment')->nullable();

            // Audit
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();


            $table->timestamps();
            $table->softDeletes();

            // Optional FK style (enable if you have those tables)
            // $table->foreign('site_id')->references('id')->on('sites');
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};
