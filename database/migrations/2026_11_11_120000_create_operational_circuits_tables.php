<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['location_id', 'code']);
        });
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['ward_id', 'code']);
        });
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['room_id', 'code']);
            $table->index(['room_id', 'is_active']);
        });
        Schema::create('emergency_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('waiting');
            $table->unsignedTinyInteger('priority')->nullable();
            $table->timestamp('arrived_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('outcome', 32)->nullable();
            $table->string('cancellation_reason', 120)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['location_id', 'status', 'arrived_at']);
            $table->index(['patient_id', 'status']);
        });
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('emergency_visit_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamp('admitted_at');
            $table->timestamp('discharged_at')->nullable();
            $table->foreignId('admitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('discharged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['patient_id', 'status']);
            $table->index(['location_id', 'status', 'admitted_at']);
        });
        Schema::create('bed_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('bed_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['bed_id', 'ended_at']);
            $table->index(['admission_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_assignments');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('emergency_visits');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('wards');
    }
};
