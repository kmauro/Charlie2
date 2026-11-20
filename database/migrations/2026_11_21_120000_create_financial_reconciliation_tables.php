<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_accounts')) {
            Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('provider', 120);
            $table->string('masked_identifier', 64);
            $table->string('currency', 3)->default('ARS');
            $table->string('kind', 24)->default('bank');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['legal_entity_id', 'is_active']);
            });
        }
        Schema::table('payment_methods', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_methods', 'financial_account_id')) {
                $table->foreignId('financial_account_id')->nullable()->after('legal_entity_id')->constrained()->nullOnDelete();
            }
        });
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'payment_method_name')) $table->string('payment_method_name', 120)->nullable()->after('payment_method_id');
            if (! Schema::hasColumn('payments', 'financial_account_id')) $table->foreignId('financial_account_id')->nullable()->after('payment_method_name')->constrained()->nullOnDelete();
        });
        Schema::table('refunds', function (Blueprint $table): void {
            if (! Schema::hasColumn('refunds', 'payment_method_name')) $table->string('payment_method_name', 120)->nullable()->after('payment_method_id');
            if (! Schema::hasColumn('refunds', 'financial_account_id')) $table->foreignId('financial_account_id')->nullable()->after('payment_method_name')->constrained()->nullOnDelete();
        });
        if (! Schema::hasTable('financial_statement_imports')) {
            Schema::create('financial_statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->string('file_name');
            $table->string('file_hash', 64);
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('row_count')->default(0);
            $table->string('status', 24)->default('completed');
            $table->timestamps();
            $table->unique(['financial_account_id', 'file_hash']);
            });
        }
        if (! Schema::hasTable('external_financial_movements')) {
            Schema::create('external_financial_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_statement_import_id')
                ->constrained('financial_statement_imports', indexName: 'external_movements_import_fk')
                ->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('external_id', 120)->nullable();
            $table->date('operated_at');
            $table->date('settled_at')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('ARS');
            $table->string('reference', 120)->nullable();
            $table->string('normalized_reference', 120)->nullable()->index();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('source_row');
            $table->timestamps();
            $table->unique(['financial_statement_import_id', 'source_row'], 'external_movement_import_row_unique');
            $table->unique(['financial_account_id', 'external_id'], 'external_movement_account_external_unique');
            $table->index(['financial_account_id', 'operated_at'], 'external_movement_account_date_index');
            });
        }
        if (! Schema::hasTable('financial_reconciliations')) {
            Schema::create('financial_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_financial_movement_id')->constrained()->restrictOnDelete();
            $table->morphs('source');
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->decimal('applied_amount', 14, 2);
            $table->string('status', 24)->default('confirmed');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['external_financial_movement_id', 'status'], 'financial_reconciliation_movement_status_index');
            });
        }
    }

    public function down(): void
    {
        // This migration is additive and must not remove tables that predate it.
    }
};
