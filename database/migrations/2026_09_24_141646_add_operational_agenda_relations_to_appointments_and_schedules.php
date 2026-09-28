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
        Schema::table('professional_schedules', function (Blueprint $table) {
            $table->foreignId('consulting_room_id')
                ->nullable()
                ->after('location_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('consulting_room_id')
                ->nullable()
                ->after('location_id')
                ->constrained()
                ->restrictOnDelete();
            $table->index(
                ['consulting_room_id', 'status', 'starts_at', 'ends_at'],
                'appointments_consulting_room_agenda_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_consulting_room_agenda_index');
            $table->dropConstrainedForeignId('consulting_room_id');
        });

        Schema::table('professional_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consulting_room_id');
        });
    }
};
