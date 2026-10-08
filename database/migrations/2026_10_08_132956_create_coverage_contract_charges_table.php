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
        Schema::create('coverage_contract_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coverage_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->string('status', 16)->default('issued');
            $table->timestamps();
            $table->unique(['coverage_contract_id', 'due_date']);
            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coverage_contract_charges');
    }
};
