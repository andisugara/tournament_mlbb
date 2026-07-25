<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $table->foreignId('team_a_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('team_b_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->integer('best_of')->default(3);
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->enum('bracket_type', ['UPPER', 'LOWER', 'GRAND_FINAL'])->nullable();
            $table->string('round_name');
            $table->string('match_code')->nullable();
            
            // Winners progression
            $table->foreignId('feeds_win_to_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->enum('feeds_win_to_slot', ['team_a', 'team_b'])->nullable();
            
            // Losers progression (e.g. drop from UB to LB)
            $table->foreignId('feeds_lose_to_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->enum('feeds_lose_to_slot', ['team_a', 'team_b'])->nullable();
            
            // Slot source match connections
            $table->foreignId('team_a_source_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->enum('team_a_source_type', ['WINNER', 'LOSER'])->nullable();
            $table->foreignId('team_b_source_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->enum('team_b_source_type', ['WINNER', 'LOSER'])->nullable();

            $table->dateTime('scheduled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
