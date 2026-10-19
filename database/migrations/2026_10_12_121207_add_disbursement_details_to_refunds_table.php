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
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->index(['cash_register_id', 'status', 'registered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropIndex(['cash_register_id', 'status', 'registered_at']);
            $table->dropConstrainedForeignId('cash_register_id');
            $table->dropColumn('reference');
        });
    }
};
