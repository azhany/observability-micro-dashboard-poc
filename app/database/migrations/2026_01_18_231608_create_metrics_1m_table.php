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
        Schema::create('metrics_1m', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('metric_name', 64);
            $table->double('avg_value', 16, 4);
            $table->timestamp('window_start', 6);

            $table->unique(['tenant_id', 'metric_name', 'window_start'], 'idx_1m_unique');
            $table->index(['tenant_id', 'metric_name', 'window_start'], 'idx_1m_tenant_metric_time');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics_1m');
    }
};
