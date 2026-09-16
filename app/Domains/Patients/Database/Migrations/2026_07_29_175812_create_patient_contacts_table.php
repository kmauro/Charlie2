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
        Schema::create('patient_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship', 120);
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('is_legal_representative')->default(false);
            $table->boolean('is_financially_responsible')->default(false);
            $table->timestamps();

            $table->index(
                ['patient_id', 'is_legal_representative'],
                'patient_contacts_legal_rep_index',
            );
            $table->index(
                ['patient_id', 'is_emergency_contact'],
                'patient_contacts_emergency_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_contacts');
    }
};
