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

        // 2. Setup 9 Teams
        $teamsData = [
            ['name' => 'Superr medmon', 'logo' => null],
            ['name' => 'Project VII', 'logo' => null],
            ['name' => 'Proximity Clan', 'logo' => null],
            ['name' => 'Tim Teknis', 'logo' => null],
            ['name' => 'Octagram', 'logo' => null],
            ['name' => 'Wo makan siang mana wo', 'logo' => null],
            ['name' => 'CROPINGIYAH', 'logo' => null],
            ['name' => 'FKON', 'logo' => null],
            ['name' => 'Solo Mix', 'logo' => null],
        ];

        $teamsPlayers = [
            'Superr medmon' => [
                'gold_lane' => 'minaaa -  71447718 (2134)',
                'exp_lane' => 'Senzuu. - 223652701 (9171)',
                'mid_lane' => 'H34THCL1FF - 61527485 (2108)',
                'jungle' => 'Kanjutt. - 669019899 (8664)',
                'roam' => 'Arssc - 37204989 (2060)',
            ],
            'Project VII' => [
                'gold_lane' => 'Andisuuu - 242766700 (9278)',
                'exp_lane' => 'Nann   Always   Fine - 593670878 (8369)',
                'mid_lane' => '~ D•Ragon ~ - 646925374 (8590)',
                'jungle' => 'Ryland Grace. - 49922916(2004)',
                'roam' => 'Reze. - 79856072 (2221)',
            ],
            'Proximity Clan' => [
                'gold_lane' => 'VIOLEnTTT - 209342882 (9075)',
                'exp_lane' => 'karlcenat - 2008502411 (19452)',
                'mid_lane' => 'waqqir - 192488083 (2986)',
                'jungle' => 'Rhapsody. -  341039557 (2232)',
                'roam' => 'EL PARFUME - 785500472 (12127)',
            ],
            'Tim Teknis' => [
                'gold_lane' => 'SugaringCandy - 59411620 (2101)',
                'exp_lane' => 'irp4ndi - 270870526 (9406)',
                'mid_lane' => 'Nakirei - 81531805 (2152)',
                'jungle' => 'Alpha - 131212857 (2670)',
                'roam' => 'Muuns Raja Garam. - 25159437 (2205)',
            ],
            'Octagram' => [
                'gold_lane' => 'DwayneJohnson - 832873158 (12313)',
                'exp_lane' => 'Sam96 -  1023318828 (13074)',
                'mid_lane' => 'izthar - 48031582 (2078)',
                'jungle' => 'ApaSalahKu - 12239898 (2013)',
                'roam' => 'B I G B O S S - 175168448 (2909)',
            ],
            'Wo makan siang mana wo' => [
                'gold_lane' => '697099 - 39754347 (2060)',
                'exp_lane' => 'Maman Silet - 59091192 (2103)',
                'mid_lane' => 'Alexander The Great - 138595883 (2695)',
                'jungle' => 'Souso - 36102807 (2057)',
                'roam' => 'Hell Kerbecs - 283263201 (15373)',
            ],
            'CROPINGIYAH' => [
                'gold_lane' => 'KyWazowSky. ✙ - 248549911 (9296)',
                'exp_lane' => 'tim pa rizky - 434975478 (2276)',
                'mid_lane' => "Wand'z - 726937318 (8904)",
                'jungle' => 'Oveer. - 169432777 (2883)',
                'roam' => 'XPANDER™ - 719940772 (8868)',
            ],
            'FKON' => [
                'gold_lane' => 'leiffTM  - 79595090 (2153)',
                'exp_lane' => 'Fachrezy25 - 32016301 (2044)',
                'mid_lane' => 'rahmatdhani - 73037492 (2041)',
                'jungle' => 'Orpheus - 115880707 (2594)',
                'roam' => 'Mr. Cky - 151571597 (2773)',
            ],
            'Solo Mix' => [
                'gold_lane' => '—n piiña - 1147457318 (13638)',
                'exp_lane' => 'Booriq - 693816276 (8769)',
                'mid_lane' => 'VendettaXDannn - 1881717888 (2966)',
                'jungle' => 'khar17zm? - 181284646 (2943)',
                'roam' => "it'sme_caca - 1189861932 (13843)",
            ],
        ];

        $teams = [];
        foreach ($teamsData as $data) {
            $team = Team::create($data);
            $teams[] = $team;

            $players = $teamsPlayers[$team->name];
            foreach ($players as $role => $playerName) {
                Player::create([
                    'team_id' => $team->id,
                    'name' => $playerName,
                    'role' => $role,
                ]);
            }
        }

        // 4. Setup Competition Setup
        $competition = CompetitionSetup::create([
            'name' => 'Kabayan Group MLBB',
            'total_teams' => 9,
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

        // 6. Generate Round Robin Matches (72 matches for 9 teams, double round robin)
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
            } elseif ($teamA->name === 'FKON') {
                $matchWinner = 1; // FKON wins against others
            } elseif ($teamB->name === 'FKON') {
                $matchWinner = 2; // FKON wins against others
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
