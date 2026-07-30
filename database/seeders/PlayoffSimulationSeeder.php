<?php

namespace Database\Seeders;

use App\Models\CompetitionSetup;
use App\Models\Stage;
use App\Models\MlMatch;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerGameStat;
use App\Models\Team;
use App\Services\BracketGeneratorService;
use App\Services\MatchPropagationService;
use Illuminate\Database\Seeder;

class PlayoffSimulationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure we have teams and competition setup
        $teams = Team::all();
        if ($teams->count() < 6) {
            $this->command->info('Seeding initial tournament first...');
            $this->call(TournamentSeeder::class);
            $teams = Team::all();
        }

        $competition = CompetitionSetup::orderBy('id', 'desc')->first();
        if (!$competition) {
            $this->command->error('No active competition setup found.');
            return;
        }

        $playoffStage = Stage::where('competition_id', $competition->id)->where('type', 'PLAYOFFS')->first();
        if (!$playoffStage) {
            $this->command->error('No playoff stage found.');
            return;
        }

        $propagation = new MatchPropagationService();

        // 2. Generate and seed playoffs if not already done
        if (MlMatch::where('stage_id', $playoffStage->id)->count() === 0) {
            $this->command->info('Generating Playoff Bracket...');
            $generator = new BracketGeneratorService();
            $generator->generate($playoffStage, 6, 2);

            // Mock standings: take top 6 teams
            $topTeamIds = $teams->take(6)->pluck('id')->toArray();
            $propagation->seedPlayoffTeams($playoffStage, $topTeamIds);
        }

        // 3. Define the sequential simulation order of matches in Double Elimination
        $matchCodes = [
            'UB_R1_M1',
            'UB_R1_M2',
            'UB_R2_M1',
            'UB_R2_M2',
            'LB_R1_M1',
            'UB_R3_M1',
            'LB_R2_M1',
            'GF_M1',
        ];

        $heroes = [
            'gold_lane' => ['Beatrix', 'Bruno', 'Harith', 'Claude', 'Karrie', 'Roger', 'Natan'],
            'exp_lane' => ['Yu Zhong', 'Terizla', 'Arlott', 'Cici', 'Benedetta', 'Paquito', 'Ruby'],
            'mid_lane' => ['Sanz', 'Novaria', 'Lylia', 'Valentina', 'Faramis', 'Yve', 'Pharsa'],
            'jungle' => ['Fanny', 'Ling', 'Lancelot', 'Baxia', 'Fredrinn', 'Barats', 'Nolan'],
            'roam' => ['Chou', 'Khufra', 'Tigreal', 'Minotaur', 'Kaja', 'Mathilda', 'Edith'],
        ];

        $this->command->info('Simulating Playoff Matches...');

        foreach ($matchCodes as $code) {
            $match = MlMatch::where('stage_id', $playoffStage->id)->where('match_code', $code)->first();

            if (!$match) {
                $this->command->warn("Match with code {$code} not found.");
                continue;
            }

            // Ensure teams are populated (propagated from previous rounds)
            if (!$match->team_a_id || !$match->team_b_id) {
                $this->command->warn("Match {$code} is missing teams (A: " . ($match->team_a_id ?? 'null') . ", B: " . ($match->team_b_id ?? 'null') . "). Skipping.");
                continue;
            }

            // Skip if match already has games simulated
            if ($match->games()->count() > 0) {
                $this->command->info("Match {$code} already has simulated games. Skipping.");
                continue;
            }

            $teamAId = $match->team_a_id;
            $teamBId = $match->team_b_id;

            // Pick winner (Enforce Project VII is champion, FKON is runner up)
            $teamA = $match->teamA;
            $teamB = $match->teamB;

            if ($teamA->name === 'Project VII') {
                $matchWinner = 1;
            } elseif ($teamB->name === 'Project VII') {
                $matchWinner = 2;
            } elseif ($teamA->name === 'FKON') {
                $matchWinner = 1;
            } elseif ($teamB->name === 'FKON') {
                $matchWinner = 2;
            } else {
                $matchWinner = rand(1, 2);
            }
            $gamesWonA = 0;
            $gamesWonB = 0;
            $targetWins = (int) floor($match->best_of / 2) + 1;

            for ($gameNum = 1; $gameNum <= $match->best_of; $gameNum++) {
                $gameWinnerId = null;
                if ($matchWinner === 1) {
                    if ($gamesWonA < $targetWins && ($gamesWonB === $targetWins - 1 || rand(1, 10) > 3)) {
                        $gameWinnerId = $teamAId;
                        $gamesWonA++;
                    } else {
                        $gameWinnerId = $teamBId;
                        $gamesWonB++;
                    }
                } else {
                    if ($gamesWonB < $targetWins && ($gamesWonA === $targetWins - 1 || rand(1, 10) > 3)) {
                        $gameWinnerId = $teamBId;
                        $gamesWonB++;
                    } else {
                        $gameWinnerId = $teamAId;
                        $gamesWonA++;
                    }
                }

                // Create game
                $game = Game::create([
                    'match_id' => $match->id,
                    'game_number' => $gameNum,
                    'winner_team_id' => $gameWinnerId,
                    'duration_seconds' => rand(720, 1500),
                ]);

                // Create player stats
                $allPlayers = Player::whereIn('team_id', [$teamAId, $teamBId])->get();
                $stats = [];
                $mvpPlayerId = null;
                $maxRating = 0.0;

                foreach ($allPlayers as $player) {
                    $isWinner = $player->team_id === $gameWinnerId;

                    $kills = $isWinner ? rand(3, 8) : rand(0, 4);
                    $deaths = $isWinner ? rand(0, 4) : rand(3, 9);
                    $assists = $isWinner ? rand(4, 15) : rand(1, 8);

                    $gold = $isWinner ? rand(9000, 16000) : rand(6000, 11000);
                    if ($player->role === 'roam') {
                        $gold = $isWinner ? rand(6000, 9000) : rand(4000, 6500);
                    }

                    $rating = $isWinner ? rand(60, 80) / 10.0 : rand(35, 60) / 10.0;
                    if ($deaths === 0) {
                        $rating += 0.5;
                    }

                    if ($rating > $maxRating) {
                        $maxRating = $rating;
                        $mvpPlayerId = $player->id;
                    }

                    $rolePool = $heroes[$player->role] ?? $heroes['jungle'];
                    $hero = $rolePool[array_rand($rolePool)];

                    $stats[$player->id] = [
                        'game_id' => $game->id,
                        'player_id' => $player->id,
                        'hero' => $hero,
                        'kills' => $kills,
                        'deaths' => $deaths,
                        'assists' => $assists,
                        'gold_earned' => $gold,
                        'rating' => $rating,
                        'is_mvp' => false,
                    ];
                }

                if (isset($stats[$mvpPlayerId])) {
                    $stats[$mvpPlayerId]['is_mvp'] = true;
                }

                foreach ($stats as $playerStat) {
                    PlayerGameStat::create($playerStat);
                }

                if ($gamesWonA === $targetWins || $gamesWonB === $targetWins) {
                    break;
                }
            }

            // Propagate results to next rounds
            $propagation->propagate($match);
            $this->command->info("Match {$code} simulated successfully! Winner: " . ($matchWinner === 1 ? $match->teamA->name : $match->teamB->name));
        }

        $this->command->info('Playoff bracket simulation seeding complete!');
    }
}
