<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nadi_telemetry_database_probe_states'))
        {
            return;
        }

        Schema::create('nadi_telemetry_database_probe_states', function (Blueprint $table)
        {
            $table->char('id', 36)
                ->charset('ascii')
                ->collation('ascii_bin')
                ->primary();

            $table->char('agent_id', 36)
                ->charset('ascii')
                ->collation('ascii_bin');

            $table->char('application_id', 36)
                ->charset('ascii')
                ->collation('ascii_bin');

            $table->char('stream_id', 36)
                ->charset('ascii')
                ->collation('ascii_bin');

            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->string('last_status', 20);
            $table->dateTime('last_probe_at', 6);
            $table->dateTime('last_success_at', 6)->nullable();
            $table->decimal('last_latency_ms', 14, 3)->nullable();
            $table->unsignedBigInteger('failures_total')->default(0);

            $table->dateTime('created_at', 6)->useCurrent();
            $table->dateTime('updated_at', 6)
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->unique(
                'stream_id',
                'uq_nadi_db_probe_state_stream'
            );

            $table->index(
                ['agent_id', 'application_id'],
                'idx_nadi_db_probe_state_scope'
            );

            $table->foreign('agent_id', 'fk_nadi_db_probe_state_agent')
                ->references('id')
                ->on('nadi_agents')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->foreign('application_id', 'fk_nadi_db_probe_state_application')
                ->references('id')
                ->on('nadi_applications')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->foreign('stream_id', 'fk_nadi_db_probe_state_stream')
                ->references('id')
                ->on('nadi_telemetry_streams')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        })
            ->engine = 'InnoDB';
    }

    public function down(): void
    {
        /*
         * Forward-only migration.
         * Probe counters and last-success state must not be removed by rollback.
         */
    }
};
