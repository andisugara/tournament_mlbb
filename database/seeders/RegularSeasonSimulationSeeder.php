<?php

namespace Database\Seeders;

use App\Models\CompetitionSetup;
use App\Models\Stage;
use App\Models\MlMatch;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerGameStat;
use App\Models\Team;
use App\Services\MatchPropagationService;
use Illuminate\Database\Seeder;

class RegularSeasonSimulationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure we have competition and matches set up
        $competition = CompetitionSetup::orderBy('id', 'desc')->first();
        if (!$competition) {
            $this->command->info('Seeding initial tournament first...');
            $this->call(TournamentSeeder::class);
            $competition = CompetitionSetup::orderBy('id', 'desc')->first();
        }

        $regularStage = Stage::where('competition_id', $competition->id)->where('type', 'REGULAR_SEASON')->first();
        if (!$regularStage) {
            $this->command->error('No regular season stage found.');
            return;
        }

        $matches = MlMatch::where('stage_id', $regularStage->id)->get();
        if ($matches->count() === 0) {
            $this->command->error('No regular season matches found to simulate. Run TournamentSeeder first.');
            return;
        }

        $propagationService = new MatchPropagationService();

        $heroes = [
            'gold_lane' => ['Beatrix', 'Bruno', 'Harith', 'Claude', 'Karrie', 'Roger', 'Natan'],
            'exp_lane' => ['Yu Zhong', 'Terizla', 'Arlott', 'Cici', 'Benedetta', 'Paquito', 'Ruby'],
            'mid_lane' => ['Sanz', 'Novaria', 'Lylia', 'Valentina', 'Faramis', 'Yve', 'Pharsa'],
            'jungle' => ['Fanny', 'Ling', 'Lancelot', 'Baxia', 'Fredrinn', 'Barats', 'Nolan'],
            'roam' => ['Chou', 'Khufra', 'Tigreal', 'Minotaur', 'Kaja', 'Mathilda', 'Edith'],
        ];

        $this->command->info('Simulating Regular Season Matches (Project VII as leader)...');

        foreach ($matches as $match) {
            // Skip if already simulated
            if ($match->games()->count() > 0) {
                continue;
            }

            $teamA = $match->teamA;
            $teamB = $match->teamB;

            $teamAId = $match->team_a_id;
            $teamBId = $match->team_b_id;

            // Enforce Project VII is champion of the group stage by winning all matches
            if ($teamA->name === 'Project VII') {
                $matchWinner = 1;
            } elseif ($teamB->name === 'Project VII') {
                $matchWinner = 2;
            } elseif ($teamA->name === 'FKON') {
                $matchWinner = 1; // FKON wins against others
            } elseif ($teamB->name === 'FKON') {
                $matchWinner = 2; // FKON wins against others
            } else {
                $matchWinner = rand(1, 2);
            }

            $gamesWonA = 0;
            $gamesWonB = 0;
            $targetWins = (int) floor($match->best_of / 2) + 1;

            for ($gameNum = 1; $gameNum <= $match->best_of; $gameNum++) {
                $gameWinnerId = null;
                if ($matchWinner == 1) {
                    if ($gamesWonA < $targetWins && ($gamesWonB == $targetWins - 1 || rand(1, 10) > 3)) {
                        $gameWinnerId = $teamAId;
                        $gamesWonA++;
                    } else {
                        $gameWinnerId = $teamBId;
                        $gamesWonB++;
                    }
                } else {
                    if ($gamesWonB < $targetWins && ($gamesWonA == $targetWins - 1 || rand(1, 10) > 3)) {
                        $gameWinnerId = $teamBId;
                        $gamesWonB++;
                    } else {
                        $gameWinnerId = $teamAId;
                        $gamesWonA++;
                    }
                }

                $game = Game::create([
                    'match_id' => $match->id,
                    'game_number' => $gameNum,
                    'winner_team_id' => $gameWinnerId,
                    'duration_seconds' => rand(720, 1500),
                ]);

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

                    // Enforce custom ratings based on player roles to rank them exactly as requested
                    $rating = 6.0;
                    if ($player->team->name === 'Project VII') {
                        switch ($player->role) {
                            case 'jungle':
                                $rating = $isWinner ? rand(98, 115) / 10.0 : rand(80, 90) / 10.0;
                                break;
                            case 'gold_lane':
                                $rating = $isWinner ? rand(97, 115) / 10.0 : rand(80, 90) / 10.0;
                                break;
                            case 'exp_lane':
                                $rating = $isWinner ? rand(83, 92) / 10.0 : rand(68, 78) / 10.0;
                                break;
                            case 'mid_lane':
                                $rating = $isWinner ? rand(88, 95) / 10.0 : rand(72, 82) / 10.0;
                                break;
                            case 'roam':
                                $rating = $isWinner ? rand(87, 95) / 10.0 : rand(72, 82) / 10.0;
                                break;
                        }
                    } elseif ($player->team->name === 'FKON') {
                        switch ($player->role) {
                            case 'mid_lane':
                                $rating = $isWinner ? rand(96, 115) / 10.0 : rand(80, 90) / 10.0;
                                break;
                            case 'roam':
                                $rating = $isWinner ? rand(95, 115) / 10.0 : rand(80, 90) / 10.0;
                                break;
                            case 'gold_lane':
                                $rating = $isWinner ? rand(85, 93) / 10.0 : rand(68, 78) / 10.0;
                                break;
                            case 'exp_lane':
                                $rating = $isWinner ? rand(90, 100) / 10.0 : rand(74, 85) / 10.0;
                                break;
                            case 'jungle':
                                $rating = $isWinner ? rand(89, 100) / 10.0 : rand(74, 85) / 10.0;
                                break;
                        }
                    } else {
                        $rating = $isWinner ? rand(60, 80) / 10.0 : rand(35, 60) / 10.0;
                    }

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

                if ($gamesWonA == $targetWins || $gamesWonB == $targetWins) {
                    break;
                }
            }

            $propagationService->propagate($match);
        }

        $this->command->info('Regular season simulation complete!');
    }
}
