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
        Schema::create('professional_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['professional_id', 'location_id', 'date'], 'professional_schedule_exceptions_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_schedule_exceptions');
    }
};
