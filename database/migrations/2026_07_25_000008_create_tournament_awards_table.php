<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competition_setups')->cascadeOnDelete();
            $table->enum('award_type', [
                'best_gold_lane',
                'best_exp_lane',
                'best_mid_lane',
                'best_jungler',
                'best_roamer',
                'overall_mvp'
            ]);
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->double('avg_rating', 4, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_awards');
    }
};
