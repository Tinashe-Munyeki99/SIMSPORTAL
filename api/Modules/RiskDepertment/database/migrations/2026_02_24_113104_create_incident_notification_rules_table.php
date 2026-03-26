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
        Schema::create('incident_notification_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('currency', 3); // USD, ZWL, ZAR etc

            $table->decimal('min_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->nullable();
            // NULL = no upper limit

            $table->boolean('is_active')->default(true);

            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_notification_rules');
    }
};
