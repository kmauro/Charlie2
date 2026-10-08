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
        Schema::create('coverage_plan_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coverage_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->decimal('coverage_percentage', 5, 2)->default(100);
            $table->decimal('copay_amount', 14, 2)->nullable();
            $table->unsignedInteger('annual_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['coverage_plan_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coverage_plan_benefits');
    }
};
