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
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('alert_rule_id');
            $table->enum('state', ['OK', 'PENDING', 'FIRING'])->default('OK');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('alert_rule_id')->references('id')->on('alert_rules')->onDelete('cascade');
            $table->index(['tenant_id', 'state']);
            $table->index(['alert_rule_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
