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
        Schema::create('appointment_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('consulting_room_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->string('frequency', 16);
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('occurrences')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->json('procedure_resources')->nullable();
            $table->string('procedure_preparation', 500)->nullable();
            $table->timestamps();
            $table->index(['professional_id', 'frequency', 'ends_on']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('appointment_series_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('requested_duration_minutes')->nullable()->after('duration_minutes');
            $table->json('procedure_resources')->nullable()->after('reason');
            $table->string('procedure_preparation', 500)->nullable()->after('procedure_resources');
        });

        Schema::create('appointment_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->default('in_person');
            $table->date('desired_from')->nullable();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->string('status', 16)->default('waiting');
            $table->string('notes', 500)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('agenda_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('consulting_room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('frequency', 16)->nullable();
            $table->date('ends_on')->nullable();
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['scope', 'starts_at', 'ends_at']);
            $table->index(['location_id', 'starts_at', 'ends_at']);
            $table->index(['professional_id', 'starts_at', 'ends_at']);
            $table->index(['consulting_room_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agenda_blocks');
        Schema::dropIfExists('appointment_waitlist_entries');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_series_id');
            $table->dropColumn(['requested_duration_minutes', 'procedure_resources', 'procedure_preparation']);
        });

        Schema::dropIfExists('appointment_series');
    }
};
