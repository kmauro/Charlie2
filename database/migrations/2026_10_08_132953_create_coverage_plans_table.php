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
        Schema::create('coverage_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->string('billing_cycle', 16)->default('monthly');
            $table->decimal('recurring_amount', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coverage_plans');
    }
};
