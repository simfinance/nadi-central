<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nadi_telemetry_batches'))
        {
            Schema::create('nadi_telemetry_batches', function (Blueprint $table)
            {
                $this->configureTable($table);
                $this->asciiUuid($table, 'id');
                $this->asciiUuid($table, 'agent_id');
                $this->asciiUuid($table, 'batch_id');

                $table->string('protocol_version', 30);
                $table->char('payload_hash', 64)->charset('ascii')->collation('ascii_bin');
                $table->dateTime('sent_at', 6);
                $table->dateTime('received_at', 6)->useCurrent();
                $table->string('batch_status', 30)->default('accepted');
                $table->unsignedInteger('record_count')->default(0);
                $table->unsignedInteger('accepted_count')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->unsignedInteger('rejected_count')->default(0);
                $table->json('ack_response')->nullable();
                $table->dateTime('created_at', 6)->useCurrent();

                $table->primary('id');
                $table->unique(['agent_id', 'batch_id'], 'uq_nadi_telemetry_batch_agent');
                $table->index('received_at', 'idx_nadi_telemetry_batch_received');
                $table->index('batch_status', 'idx_nadi_telemetry_batch_status');

                $table->foreign('agent_id', 'fk_nadi_telemetry_batches_agent')
                    ->references('id')->on('nadi_agents')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (!Schema::hasTable('nadi_telemetry_streams'))
        {
            Schema::create('nadi_telemetry_streams', function (Blueprint $table)
            {
                $this->configureTable($table);
                $this->asciiUuid($table, 'id');
                $this->asciiUuid($table, 'agent_id');
                $this->asciiUuid($table, 'application_id')->nullable();

                $table->char('stream_key', 64)->charset('ascii')->collation('ascii_bin');
                $table->string('stream_name', 100);
                $table->string('source', 30);
                $table->unsignedBigInteger('last_contiguous_sequence')->default(0);
                $table->unsignedBigInteger('max_sequence_seen')->default(0);
                $table->dateTime('last_observed_at', 6)->nullable();
                $table->dateTime('last_received_at', 6)->nullable();
                $table->dateTime('created_at', 6)->useCurrent();
                $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

                $table->primary('id');
                $table->unique(['agent_id', 'stream_key'], 'uq_nadi_telemetry_stream_key');
                $table->index('application_id', 'idx_nadi_telemetry_stream_application');
                $table->index('stream_name', 'idx_nadi_telemetry_stream_name');

                $table->foreign('agent_id', 'fk_nadi_telemetry_streams_agent')
                    ->references('id')->on('nadi_agents')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('application_id', 'fk_nadi_telemetry_streams_application')
                    ->references('id')->on('nadi_applications')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (!Schema::hasTable('nadi_telemetry_record_keys'))
        {
            Schema::create('nadi_telemetry_record_keys', function (Blueprint $table)
            {
                $this->configureTable($table);
                $this->asciiUuid($table, 'id');
                $this->asciiUuid($table, 'agent_id');
                $this->asciiUuid($table, 'stream_id');
                $this->asciiUuid($table, 'record_id');

                $table->unsignedBigInteger('sequence');
                $table->char('payload_hash', 64)->charset('ascii')->collation('ascii_bin');
                $table->dateTime('first_seen_at', 6)->useCurrent();
                $table->dateTime('last_seen_at', 6)->useCurrent();
                $table->dateTime('created_at', 6)->useCurrent();

                $table->primary('id');
                $table->unique(['agent_id', 'record_id'], 'uq_nadi_telemetry_record_key');
                $table->unique(['stream_id', 'sequence'], 'uq_nadi_telemetry_record_key_sequence');
                $table->index('last_seen_at', 'idx_nadi_telemetry_record_keys_seen');

                $table->foreign('agent_id', 'fk_nadi_telemetry_record_keys_agent')
                    ->references('id')->on('nadi_agents')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('stream_id', 'fk_nadi_telemetry_record_keys_stream')
                    ->references('id')->on('nadi_telemetry_streams')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (!Schema::hasTable('nadi_telemetry_records'))
        {
            Schema::create('nadi_telemetry_records', function (Blueprint $table)
            {
                $this->configureTable($table);
                $this->asciiUuid($table, 'id');
                $this->asciiUuid($table, 'batch_id');
                $this->asciiUuid($table, 'stream_id');
                $this->asciiUuid($table, 'agent_id');
                $this->asciiUuid($table, 'application_id')->nullable();
                $this->asciiUuid($table, 'record_id');

                $table->unsignedBigInteger('sequence');
                $table->dateTime('observed_at', 6);
                $table->dateTime('collected_at', 6);
                $table->json('data');
                $table->dateTime('created_at', 6)->useCurrent();

                $table->primary('id');
                $table->unique(['stream_id', 'sequence'], 'uq_nadi_telemetry_record_sequence');
                $table->index(['agent_id', 'observed_at'], 'idx_nadi_telemetry_records_agent_time');
                $table->index(['application_id', 'observed_at'], 'idx_nadi_telemetry_records_application_time');
                $table->index('batch_id', 'idx_nadi_telemetry_records_batch');

                $table->foreign('agent_id', 'fk_nadi_telemetry_records_agent')
                    ->references('id')->on('nadi_agents')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('application_id', 'fk_nadi_telemetry_records_application')
                    ->references('id')->on('nadi_applications')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('batch_id', 'fk_nadi_telemetry_records_batch')
                    ->references('id')->on('nadi_telemetry_batches')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('stream_id', 'fk_nadi_telemetry_records_stream')
                    ->references('id')->on('nadi_telemetry_streams')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (!Schema::hasTable('nadi_http_request_events'))
        {
            Schema::create('nadi_http_request_events', function (Blueprint $table)
            {
                $this->configureTable($table);
                $this->asciiUuid($table, 'id');
                $this->asciiUuid($table, 'batch_id');
                $this->asciiUuid($table, 'stream_id');
                $this->asciiUuid($table, 'agent_id');
                $this->asciiUuid($table, 'application_id')->nullable();
                $this->asciiUuid($table, 'record_id');

                $table->string('request_id', 100)->nullable();
                $table->string('source', 20);
                $table->string('event_status', 30)->default('completed');
                $table->unsignedBigInteger('sequence');
                $table->dateTime('observed_at', 6);
                $table->string('client_ip', 45)->nullable();
                $table->string('http_method', 16);
                $table->string('host', 255)->nullable();
                $table->string('request_uri', 2048)->nullable();
                $table->string('route', 512)->nullable();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->unsignedBigInteger('request_size_bytes')->nullable();
                $table->unsignedBigInteger('response_size_bytes')->nullable();
                $table->decimal('duration_ms', 14, 3)->nullable();
                $table->decimal('upstream_response_time_ms', 14, 3)->nullable();
                $table->string('exception_class', 255)->nullable();
                $table->string('error_message', 2000)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->json('metadata')->nullable();
                $table->dateTime('created_at', 6)->useCurrent();

                $table->primary('id');
                $table->unique(['agent_id', 'record_id'], 'uq_nadi_http_event_record');
                $table->unique(['stream_id', 'sequence'], 'uq_nadi_http_event_sequence');
                $table->index(['application_id', 'observed_at'], 'idx_nadi_http_application_time');
                $table->index(['client_ip', 'observed_at'], 'idx_nadi_http_client_ip_time');
                $table->index(['http_status', 'observed_at'], 'idx_nadi_http_status_time');
                $table->index('request_id', 'idx_nadi_http_request_id');
                $table->index(['http_method', 'http_status', 'observed_at'], 'idx_nadi_http_method_status_time');
                $table->index(['host', 'observed_at'], 'idx_nadi_http_host_time');
                $table->index(['source', 'observed_at'], 'idx_nadi_http_source_time');
                $table->index(['event_status', 'observed_at'], 'idx_nadi_http_event_status_time');
                $table->index('batch_id', 'idx_nadi_http_batch');

                $table->foreign('agent_id', 'fk_nadi_http_events_agent')
                    ->references('id')->on('nadi_agents')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('application_id', 'fk_nadi_http_events_application')
                    ->references('id')->on('nadi_applications')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('batch_id', 'fk_nadi_http_events_batch')
                    ->references('id')->on('nadi_telemetry_batches')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('stream_id', 'fk_nadi_http_events_stream')
                    ->references('id')->on('nadi_telemetry_streams')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (!Schema::hasTable('nadi_telemetry_gaps'))
        {
            Schema::create('nadi_telemetry_gaps', function (Blueprint $table)
            {
                $this->configureTable($table);
                $this->asciiUuid($table, 'id');
                $this->asciiUuid($table, 'stream_id');
                $this->asciiUuid($table, 'agent_id');

                $table->unsignedBigInteger('missing_sequence_from')->nullable();
                $table->unsignedBigInteger('missing_sequence_to')->nullable();
                $table->dateTime('gap_started_at', 6);
                $table->dateTime('gap_detected_at', 6)->useCurrent();
                $table->dateTime('gap_resolved_at', 6)->nullable();
                $table->string('gap_status', 30)->default('open');
                $table->string('description', 500)->nullable();
                $table->dateTime('created_at', 6)->useCurrent();
                $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

                $table->primary('id');
                $table->index(['stream_id', 'gap_status'], 'idx_nadi_telemetry_gaps_stream_status');
                $table->index(['agent_id', 'gap_detected_at'], 'idx_nadi_telemetry_gaps_agent_detected');

                $table->foreign('agent_id', 'fk_nadi_telemetry_gaps_agent')
                    ->references('id')->on('nadi_agents')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('stream_id', 'fk_nadi_telemetry_gaps_stream')
                    ->references('id')->on('nadi_telemetry_streams')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }
    }

    public function down(): void
    {
        /*
         * Intentionally forward-only.
         *
         * Telemetry tables may contain operational history. A normal
         * migration rollback must not silently delete that data.
         */
    }

    private function configureTable(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_0900_ai_ci';
    }

    private function asciiUuid(Blueprint $table, string $column): void
    {
        $table->char($column, 36)
            ->charset('ascii')
            ->collation('ascii_bin');
    }
};
