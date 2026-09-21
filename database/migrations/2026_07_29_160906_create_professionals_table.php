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
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('document_number', 20)->unique();
            $table->string('phone', 32)->nullable();
            $table->string('specialty', 120)->index();
            $table->string('registration_type', 20);
            $table->string('registration_number', 50);
            $table->unsignedSmallInteger('appointment_duration_minutes');
            $table->string('status', 32)->default('active')->index();
            $table->string('office_assignment_type', 32)->default('availability');
            $table->string('fixed_office', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['registration_type', 'registration_number'],
                'professionals_registration_unique',
            );
            $table->index(
                ['status', 'specialty'],
                'professionals_status_specialty_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
