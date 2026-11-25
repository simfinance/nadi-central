<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nadi_alerts', function (Blueprint $table)
        {
            $table->uuid('id')->primary();
            $table->string('fingerprint', 128)->unique();
            $table->string('status', 20)->index();
            $table->string('alertname', 255)->index();
            $table->string('severity', 100)->nullable()->index();
            $table->string('summary', 1000)->nullable();
            $table->text('description')->nullable();
            $table->json('labels');
            $table->json('annotations')->nullable();
            $table->string('group_key', 512)->nullable();
            $table->string('receiver', 255)->nullable();
            $table->text('generator_url')->nullable();
            $table->text('external_url')->nullable();
            $table->dateTime('starts_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();
            $table->dateTime('last_received_at', 6);
            $table->timestamps(6);

            $table->index(['status', 'last_received_at']);
            $table->index(['alertname', 'severity']);
        });

        Schema::create('nadi_alert_events', function (Blueprint $table)
        {
            $table->uuid('id')->primary();
            $table->char('event_key', 64)->unique();
            $table->string('fingerprint', 128)->index();
            $table->string('status', 20)->index();
            $table->string('alertname', 255)->index();
            $table->string('severity', 100)->nullable()->index();
            $table->string('summary', 1000)->nullable();
            $table->text('description')->nullable();
            $table->json('labels');
            $table->json('annotations')->nullable();
            $table->string('group_key', 512)->nullable();
            $table->string('receiver', 255)->nullable();
            $table->text('generator_url')->nullable();
            $table->text('external_url')->nullable();
            $table->dateTime('starts_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();
            $table->dateTime('received_at', 6)->index();
            $table->dateTime('created_at', 6);

            $table->index(['fingerprint', 'received_at']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nadi_alert_events');
        Schema::dropIfExists('nadi_alerts');
    }
};
