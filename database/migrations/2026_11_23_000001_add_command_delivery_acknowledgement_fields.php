<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nadi_agent_commands'))
        {
            return;
        }

        Schema::table('nadi_agent_commands', function (Blueprint $table)
        {
            if (!Schema::hasColumn('nadi_agent_commands', 'acknowledged_at'))
            {
                $table->timestamp('acknowledged_at')->nullable();
            }

            if (!Schema::hasColumn('nadi_agent_commands', 'delivery_attempts'))
            {
                $table->unsignedInteger('delivery_attempts')
                    ->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('nadi_agent_commands'))
        {
            return;
        }

        Schema::table('nadi_agent_commands', function (Blueprint $table)
        {
            $columns = [];

            if (Schema::hasColumn('nadi_agent_commands', 'acknowledged_at'))
            {
                $columns[] = 'acknowledged_at';
            }

            if (Schema::hasColumn('nadi_agent_commands', 'delivery_attempts'))
            {
                $columns[] = 'delivery_attempts';
            }

            if ($columns !== [])
            {
                $table->dropColumn($columns);
            }
        });
    }
};
