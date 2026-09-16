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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 20)->unique();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->date('birth_date')->index();
            $table->string('registered_sex', 32);
            $table->string('gender', 100)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('coverage_name', 120)->index();
            $table->string('member_number', 80)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['status', 'last_name', 'first_name'],
                'patients_status_name_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
