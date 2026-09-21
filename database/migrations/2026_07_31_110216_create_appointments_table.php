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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('type', 32);
            $table->string('status', 32)->default('reserved');
            $table->string('office', 120)->nullable();
            $table->string('reason', 500)->nullable();
            $table->boolean('is_overbook')->default(false);
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(
                ['professional_id', 'status', 'starts_at', 'ends_at'],
                'appointments_professional_agenda_index',
            );
            $table->index(
                ['patient_id', 'status', 'starts_at', 'ends_at'],
                'appointments_patient_agenda_index',
            );
            $table->index(
                ['location_id', 'office', 'status', 'starts_at'],
                'appointments_office_agenda_index',
            );
            $table->index(['starts_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
