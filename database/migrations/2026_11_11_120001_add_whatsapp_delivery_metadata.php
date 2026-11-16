<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_notification_preferences', function (Blueprint $table) {
            $table->string('whatsapp_phone', 32)->nullable()->after('appointment_reminders_by_whatsapp');
            $table->timestamp('whatsapp_consented_at')->nullable()->after('whatsapp_phone');
            $table->timestamp('whatsapp_revoked_at')->nullable()->after('whatsapp_consented_at');
        });
        Schema::table('appointment_reminder_deliveries', function (Blueprint $table) {
            $table->string('provider_message_id', 191)->nullable()->after('failure_reason');
            $table->string('idempotency_key', 100)->nullable()->unique()->after('provider_message_id');
            $table->timestamp('accepted_at')->nullable()->after('idempotency_key');
        });
    }
    public function down(): void
    {
        Schema::table('appointment_reminder_deliveries', function (Blueprint $table) { $table->dropUnique(['idempotency_key']); $table->dropColumn(['provider_message_id', 'idempotency_key', 'accepted_at']); });
        Schema::table('patient_notification_preferences', function (Blueprint $table) { $table->dropColumn(['whatsapp_phone', 'whatsapp_consented_at', 'whatsapp_revoked_at']); });
    }
};
