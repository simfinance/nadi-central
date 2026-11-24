<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->text('totp_secret')->nullable()->after('password');
            $table->timestamp('totp_enabled_at')->nullable()->after('totp_secret');
            $table->unsignedBigInteger('totp_last_used_step')->nullable()->after('totp_enabled_at');
        });

        Schema::create('user_mfa_recovery_codes', function (Blueprint $table)
        {
            $table->id();
            $table->char('user_id', 36)->charset('ascii')->collation('ascii_bin');
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'used_at']);
        });

        Schema::create('user_mfa_audits', function (Blueprint $table)
        {
            $table->id();
            $table->char('user_id', 36)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->string('event', 80);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_audits');
        Schema::dropIfExists('user_mfa_recovery_codes');

        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn([
                'totp_secret',
                'totp_enabled_at',
                'totp_last_used_step',
            ]);
        });
    }
};
