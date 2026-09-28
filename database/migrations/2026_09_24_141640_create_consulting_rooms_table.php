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
        Schema::create('consulting_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 50);
            $table->string('sector', 120)->nullable();
            $table->string('floor', 50)->nullable();
            $table->json('equipment')->nullable();
            $table->string('operational_restrictions', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['location_id', 'code']);
            $table->index(['location_id', 'is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consulting_rooms');
    }
};
