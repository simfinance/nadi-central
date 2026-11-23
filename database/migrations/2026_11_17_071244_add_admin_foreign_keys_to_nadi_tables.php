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
        Schema::table('nadi_approval_logs', function (Blueprint $table) {
            $table->foreign('admin_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->restrictOnUpdate();
        });

        Schema::table('nadi_registrations', function (Blueprint $table) {
            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->restrictOnUpdate();
        });

        Schema::table('nadi_enrollment_tokens', function (Blueprint $table) {
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nadi_enrollment_tokens', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('nadi_registrations', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
        });

        Schema::table('nadi_approval_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });
    }
};
