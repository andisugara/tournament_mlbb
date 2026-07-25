<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_setups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('total_teams');
            $table->integer('teams_advance_to_playoff');
            $table->integer('upper_bracket_direct_seed');
            $table->integer('regular_season_best_of')->default(3);
            $table->integer('playoff_upper_best_of')->default(3);
            $table->integer('playoff_lower_best_of')->default(3);
            $table->integer('playoff_gf_best_of')->default(7);
            $table->boolean('is_double_round_robin')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_setups');
    }
};
