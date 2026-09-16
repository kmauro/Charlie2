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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('id')
                ->constrained('roles')
                ->restrictOnDelete();
            $table->string('status')->default('active')->index();
            $table->json('permissions')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->char('invitation_token_hash', 64)->nullable()->unique();
            $table->timestamp('invitation_expires_at')->nullable()->index();
            $table->timestamp('invitation_accepted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropUnique(['invitation_token_hash']);
            $table->dropIndex(['status']);
            $table->dropIndex(['invitation_expires_at']);
            $table->dropColumn([
                'status',
                'permissions',
                'last_login_at',
                'invitation_token_hash',
                'invitation_expires_at',
                'invitation_accepted_at',
            ]);
        });
    }
};
