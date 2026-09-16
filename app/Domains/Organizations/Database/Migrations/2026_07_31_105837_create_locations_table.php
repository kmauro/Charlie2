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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('name')->index();
            $table->string('code', 20)->unique();
            $table->text('address');
            $table->string('neighborhood', 120)->nullable()->index();
            $table->string('postal_code', 16)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable()->index();
            $table->date('opening_date')->nullable();
            $table->time('opening_time');
            $table->time('closing_time');
            $table->string('timezone', 64)
                ->default('America/Argentina/Buenos_Aires');
            $table->json('services');
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['legal_entity_id', 'status'],
                'locations_legal_entity_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
