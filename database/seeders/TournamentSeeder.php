<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Player;
use App\Models\CompetitionSetup;
use App\Models\Stage;
use App\Models\MlMatch;
use App\Models\Game;
use App\Models\PlayerGameStat;
use App\Models\User;
use App\Services\MatchPropagationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create admin user
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Panitia Turnamen',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Setup 8 Teams
        $teamsData = [
            ['name' => 'Project VII', 'logo' => null],
            ['name' => 'Clan Mia', 'logo' => null],
            ['name' => 'falkon knight', 'logo' => null],
            ['name' => 'Superr Medmon', 'logo' => null],
            ['name' => 'Ihsani Tim', 'logo' => null],
            ['name' => 'Fikri Tim', 'logo' => null],
            ['name' => 'kurasi 1', 'logo' => null],
            ['name' => 'Produksi', 'logo' => null],
        ];

        $teams = [];
        foreach ($teamsData as $data) {
            $teams[] = Team::create($data);
        }

        // 3. Setup Players (5 players per team with distinct roles)
        $roles = ['gold_lane', 'exp_lane', 'mid_lane', 'jungle', 'roam'];
        $playerNames = [
            'gold_lane' => ['Kelra', 'Skylar', 'CW', 'Caderaa', 'Eman', 'Nino', 'Watt', 'Dee'],
            'exp_lane' => ['Fluffy', 'Dyrennn', 'Lutpii', 'Luke', 'Super Kenn', 'Papi Chulo', 'Kimura', 'Karss'],
            'mid_lane' => ['Claw Kun', 'Clayyy', 'Sanz', 'Aboy', 'Moreno', 'Cr1sty', 'Keyz', 'SwayLow'],
            'jungle' => ['Anavel', 'Sutsujin', 'Kairi', 'Reyy', 'Kenn', 'Tazz', 'Rey', 'Vincentt'],
            'roam' => ['Dreams', 'Idok', 'Kiboy', 'Baloyskie', 'Kyy', 'Rasy', 'Muezza', 'AudyTzy'],
        ];

        foreach ($teams as $index => $team) {
            foreach ($roles as $role) {
                $name = '';
                if ($team->name === 'Project VII') {
                    if ($role === 'gold_lane') $name = 'gara';
                    elseif ($role === 'exp_lane') $name = 'nandi';
                    elseif ($role === 'mid_lane') $name = 'agung';
                    elseif ($role === 'jungle') $name = 'pathan';
                    elseif ($role === 'roam') $name = 'maul';
                } elseif ($team->name === 'falkon knight') {
                    if ($role === 'gold_lane') $name = 'rendy';
                    elseif ($role === 'exp_lane') $name = 'ezy';
                    elseif ($role === 'mid_lane') $name = 'Rahmat dhani';
                    elseif ($role === 'jungle') $name = 'fajar';
                    elseif ($role === 'roam') $name = 'cky';
                } else {
                    $name = $team->name . ' ' . $playerNames[$role][$index];
                }

                Player::create([
                    'team_id' => $team->id,
                    'name' => $name,
                    'role' => $role,
                ]);
            }
        }

        // 4. Setup Competition Setup
        $competition = CompetitionSetup::create([
            'name' => 'Kabayan Group MLBB',
            'total_teams' => 8,
            'teams_advance_to_playoff' => 6,
            'upper_bracket_direct_seed' => 2,
            'regular_season_best_of' => 3,
            'playoff_upper_best_of' => 3,
            'playoff_lower_best_of' => 3,
            'playoff_gf_best_of' => 5,
            'is_double_round_robin' => true,
        ]);

        // 5. Setup Stages (Regular Season and Playoffs)
        $regularStage = Stage::create([
            'competition_id' => $competition->id,
            'type' => 'REGULAR_SEASON',
            'format' => 'ROUND_ROBIN',
        ]);

        // Create empty Playoff Stage as placeholder
        $playoffStage = Stage::create([
            'competition_id' => $competition->id,
            'type' => 'PLAYOFFS',
            'format' => 'DOUBLE_ELIMINATION',
        ]);

        // 6. Generate Round Robin Matches (56 matches for 8 teams, double round robin)
        $matchPairs = [];
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $matchPairs[] = [$teams[$i], $teams[$j]]; // Home
                $matchPairs[] = [$teams[$j], $teams[$i]]; // Away
            }
        }

        // Shuffle pairs to make round scheduling look natural
        shuffle($matchPairs);

        $matches = [];
        $scheduledTime = now()->subDays(15);

        foreach ($matchPairs as $idx => $pair) {
            $matches[] = MlMatch::create([
                'stage_id' => $regularStage->id,
                'team_a_id' => $pair[0]->id,
                'team_b_id' => $pair[1]->id,
                'best_of' => $competition->regular_season_best_of,
                'round_name' => 'Week ' . (int) floor($idx / 8 + 1),
                'scheduled_at' => $scheduledTime->copy()->addHours($idx * 3),
            ]);
        }

        // 7. Simulate results for all matches to complete the group stage
        $propagationService = new MatchPropagationService();
        $heroes = [
            'gold_lane' => ['Beatrix', 'Bruno', 'Harith', 'Claude', 'Karrie', 'Roger', 'Natan'],
            'exp_lane' => ['Yu Zhong', 'Terizla', 'Arlott', 'Cici', 'Benedetta', 'Paquito', 'Ruby'],
            'mid_lane' => ['Sanz', 'Novaria', 'Lylia', 'Valentina', 'Faramis', 'Yve', 'Pharsa'],
            'jungle' => ['Fanny', 'Ling', 'Lancelot', 'Baxia', 'Fredrinn', 'Barats', 'Nolan'],
            'roam' => ['Chou', 'Khufra', 'Tigreal', 'Minotaur', 'Kaja', 'Mathilda', 'Edith'],
        ];

        for ($mIdx = 0; $mIdx < count($matches); $mIdx++) {
            $match = $matches[$mIdx];
            $teamA = $match->teamA;
            $teamB = $match->teamB;

            $teamAId = $match->team_a_id;
            $teamBId = $match->team_b_id;

            // Enforce Project VII is champion of the group stage by winning all matches
            if ($teamA->name === 'Project VII') {
                $matchWinner = 1;
            } elseif ($teamB->name === 'Project VII') {
                $matchWinner = 2;
            } elseif ($teamA->name === 'falkon knight') {
                $matchWinner = 1; // falkon knight wins against others
            } elseif ($teamB->name === 'falkon knight') {
                $matchWinner = 2; // falkon knight wins against others
            } else {
                $matchWinner = rand(1, 2);
            }

            $gamesWonA = 0;
            $gamesWonB = 0;

            for ($gameNum = 1; $gameNum <= 3; $gameNum++) {
                $gameWinnerId = null;
                if ($matchWinner == 1) {
                    if ($gamesWonA < 2 && ($gamesWonB == 1 || rand(1, 10) > 3)) {
                        $gameWinnerId = $teamAId;
                        $gamesWonA++;
                    } else {
                        $gameWinnerId = $teamBId;
                        $gamesWonB++;
                    }
                } else {
                    if ($gamesWonB < 2 && ($gamesWonA == 1 || rand(1, 10) > 3)) {
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

                    // Enforce custom ratings based on player names to rank them exactly as requested
                    $rating = 6.0;
                    switch ($player->name) {
                        // Project VII
                        case 'pathan': // jungle top 1
                            $rating = $isWinner ? rand(98, 115) / 10.0 : rand(80, 90) / 10.0;
                            break;
                        case 'gara': // gold top 1
                            $rating = $isWinner ? rand(97, 115) / 10.0 : rand(80, 90) / 10.0;
                            break;
                        case 'nandi': // exp top 3
                            $rating = $isWinner ? rand(83, 92) / 10.0 : rand(68, 78) / 10.0;
                            break;
                        case 'agung': // mid top 2
                            $rating = $isWinner ? rand(88, 95) / 10.0 : rand(72, 82) / 10.0;
                            break;
                        case 'maul': // roam top 2
                            $rating = $isWinner ? rand(87, 95) / 10.0 : rand(72, 82) / 10.0;
                            break;

                        // falkon knight
                        case 'Rahmat dhani': // mid top 1
                            $rating = $isWinner ? rand(96, 115) / 10.0 : rand(80, 90) / 10.0;
                            break;
                        case 'cky': // roam top 1
                            $rating = $isWinner ? rand(95, 115) / 10.0 : rand(80, 90) / 10.0;
                            break;
                        case 'rendy': // gold top 3
                            $rating = $isWinner ? rand(85, 93) / 10.0 : rand(68, 78) / 10.0;
                            break;
                        case 'ezy': // exp top 2
                            $rating = $isWinner ? rand(90, 100) / 10.0 : rand(74, 85) / 10.0;
                            break;
                        case 'fajar': // jungle top 2
                            $rating = $isWinner ? rand(89, 100) / 10.0 : rand(74, 85) / 10.0;
                            break;

                        default:
                            $rating = $isWinner ? rand(60, 80) / 10.0 : rand(35, 60) / 10.0;
                            break;
                    }

                    if ($deaths === 0) $rating += 0.5;

                    if ($rating > $maxRating) {
                        $maxRating = $rating;
                        $mvpPlayerId = $player->id;
                    }

                    $rolePool = $heroes[$player->role];
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

                if ($gamesWonA == 2 || $gamesWonB == 2) {
                    break;
                }
            }

            $propagationService->propagate($match);
        }
    }
}
