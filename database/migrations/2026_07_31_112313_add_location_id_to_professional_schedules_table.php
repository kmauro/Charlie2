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
        // MySQL can commit the column before Laravel records the migration.
        if (! Schema::hasColumn('professional_schedules', 'location_id')) {
            Schema::table('professional_schedules', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('location_name');
            });
        }

        if (! Schema::hasForeignKey('professional_schedules', ['location_id'])) {
            Schema::table('professional_schedules', function (Blueprint $table) {
                $table->foreign('location_id')->references('id')->on('locations')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professional_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
