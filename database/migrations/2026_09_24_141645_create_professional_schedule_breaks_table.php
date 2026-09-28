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
        Schema::create('professional_schedule_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_schedule_id')->constrained()->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label', 120)->nullable();
            $table->timestamps();

            $table->index(['professional_schedule_id', 'start_time', 'end_time'], 'professional_schedule_breaks_range_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_schedule_breaks');
    }
};
