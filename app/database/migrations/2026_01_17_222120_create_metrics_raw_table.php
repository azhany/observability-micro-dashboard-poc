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
        Schema::create('metrics_raw', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('agent_id', 64);
            $table->string('metric_name', 64);
            $table->double('value', 16, 4);
            $table->timestamp('timestamp', 6);
            $table->string('dedupe_id', 128)->nullable();

            $table->unique(['tenant_id', 'dedupe_id']);
            $table->index(['tenant_id', 'metric_name', 'timestamp'], 'idx_tenant_metric_time');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics_raw');
    }
};
