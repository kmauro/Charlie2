<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableDoesntHaveColumn('locations', 'arca_point_of_sale', function (Blueprint $table) {
            $table->unsignedSmallInteger('arca_point_of_sale')->nullable()->after('timezone');
        });

        if (! Schema::hasTable('fiscal_documents')) {
            Schema::create('fiscal_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id');
                $table->foreignId('location_id');
                $table->foreignId('requested_by');
                $table->string('environment', 16);
                $table->string('issuer_cuit', 16);
                $table->unsignedSmallInteger('point_of_sale');
                $table->unsignedSmallInteger('document_type');
                $table->string('status', 16)->default('pending');
                $table->unsignedBigInteger('fiscal_number')->nullable();
                $table->date('fiscal_date');
                $table->json('request_snapshot');
                $table->string('request_hash', 64);
                $table->string('idempotency_key', 100);
                $table->string('cae', 32)->nullable();
                $table->date('cae_expires_at')->nullable();
                $table->text('observations')->nullable();
                $table->timestamp('authorized_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        // A previous MySQL attempt may have created the table before adding its keys.
        $this->ensureFiscalDocumentKeys();

        if (! Schema::hasTable('fiscal_document_attempts')) {
            Schema::create('fiscal_document_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fiscal_document_id');
                $table->string('status', 16);
                $table->unsignedBigInteger('fiscal_number')->nullable();
                $table->string('provider_code', 64)->nullable();
                $table->text('message')->nullable();
                $table->string('correlation_id', 100)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('fiscal_document_attempts', function (Blueprint $table) {
            if (! Schema::hasForeignKey('fiscal_document_attempts', ['fiscal_document_id'])) {
                $table->foreign('fiscal_document_id')->references('id')->on('fiscal_documents')->cascadeOnDelete();
            }

            if (! Schema::hasIndex('fiscal_document_attempts', ['fiscal_document_id', 'created_at'])) {
                $table->index(['fiscal_document_id', 'created_at']);
            }
        });
    }

    private function ensureFiscalDocumentKeys(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            foreach (['invoice_id' => 'invoices', 'location_id' => 'locations', 'requested_by' => 'users'] as $column => $parent) {
                if (! Schema::hasForeignKey('fiscal_documents', [$column])) {
                    $table->foreign($column)->references('id')->on($parent)->restrictOnDelete();
                }
            }

            foreach (['invoice_id', 'idempotency_key'] as $column) {
                if (! Schema::hasIndex('fiscal_documents', [$column], 'unique')) {
                    $table->unique($column);
                }
            }

            $series = ['environment', 'issuer_cuit', 'point_of_sale', 'document_type'];
            if (! Schema::hasIndex('fiscal_documents', [...$series, 'status'])) {
                $table->index([...$series, 'status'], 'fiscal_documents_series_status_index');
            }

            if (! Schema::hasIndex('fiscal_documents', [...$series, 'fiscal_number'], 'unique')) {
                $table->unique([...$series, 'fiscal_number'], 'fiscal_documents_authorized_number_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_document_attempts');
        Schema::dropIfExists('fiscal_documents');
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('arca_point_of_sale');
        });
    }
};
