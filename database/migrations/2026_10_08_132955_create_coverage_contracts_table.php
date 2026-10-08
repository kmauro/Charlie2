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
        Schema::create('coverage_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('coverage_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('member_number', 80)->nullable();
            $table->string('payer_type', 16)->default('patient');
            $table->decimal('coverage_amount', 14, 2)->default(0);
            $table->decimal('recurring_amount', 14, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_charge_on');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->index(['location_id', 'status', 'next_charge_on']);
            $table->index(['patient_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coverage_contracts');
    }
};
