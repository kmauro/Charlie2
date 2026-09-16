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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('event', 160)->index();
            $table->string('auditable_type', 191);
            $table->string('auditable_id', 191)->nullable();
            $table->longText('before_values')->nullable();
            $table->longText('after_values')->nullable();
            $table->string('outcome', 32)->default('success')->index();
            $table->text('reason')->nullable();
            $table->string('correlation_id', 100)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(
                ['auditable_type', 'auditable_id'],
                'audit_logs_auditable_index',
            );
            $table->index(
                ['actor_id', 'created_at'],
                'audit_logs_actor_created_index',
            );
            $table->index(
                ['event', 'created_at'],
                'audit_logs_event_created_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
