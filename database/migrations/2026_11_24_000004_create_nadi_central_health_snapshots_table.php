<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nadi_central_health_snapshots', function (Blueprint $table)
        {
            $table->id();
            $table->timestamp('sampled_at');
            $table->string('overall_status', 20)->default('unknown');
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('info_count')->default(0);
            $table->json('snapshot_payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index('sampled_at');
            $table->index(['overall_status', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nadi_central_health_snapshots');
    }
};
