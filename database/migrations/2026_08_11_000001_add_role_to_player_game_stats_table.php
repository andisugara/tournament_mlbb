<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_game_stats', function (Blueprint $table) {
            $table->string('role')->nullable()->after('player_id');
        });

        // Initialize existing records with player's registered role (cross-compatible SQL)
        DB::statement("
            UPDATE player_game_stats
            SET role = (SELECT role FROM players WHERE players.id = player_game_stats.player_id)
            WHERE role IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('player_game_stats', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
