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
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('payer_type', 16)->default('patient')->after('patient_id');
            $table->string('coverage_name', 120)->nullable()->after('payer_type');
            $table->decimal('coverage_amount', 14, 2)->default(0)->after('total_amount');
            $table->foreignId('canceled_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('canceled_at')->nullable()->after('issued_at');
            $table->string('cancellation_reason', 500)->nullable()->after('canceled_at');
            $table->index(['legal_entity_id', 'payer_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['legal_entity_id', 'payer_type']);
            $table->dropConstrainedForeignId('canceled_by');
            $table->dropColumn(['payer_type', 'coverage_name', 'coverage_amount', 'canceled_at', 'cancellation_reason']);
        });
    }
};
