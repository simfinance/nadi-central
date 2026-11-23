
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)
                ->nullable()
                ->unique()
                ->after('name');
        });

        DB::table('users')
            ->where('id', '01a1e85e-acd7-7d02-96f2-08357b349268')
            ->update([
                'username' => '117.02.252',
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
