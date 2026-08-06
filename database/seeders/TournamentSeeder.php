<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Player;
use App\Models\User;
use App\Models\CompetitionSetup;
use App\Models\Stage;
use App\Models\MlMatch;
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
            'is_double_round_robin' => false,
        ]);

        // 5. Setup Stages (Regular Season and Playoffs)
        $regularStage = Stage::create([
            'competition_id' => $competition->id,
            'type' => 'REGULAR_SEASON',
            'format' => 'ROUND_ROBIN',
        ]);

        $playoffStage = Stage::create([
            'competition_id' => $competition->id,
            'type' => 'PLAYOFFS',
            'format' => 'DOUBLE_ELIMINATION',
        ]);

        // 6. Generate Round Robin Matches (Single Round Robin = 36 matches)
        $matchPairs = [];
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $matchPairs[] = [$teams[$i], $teams[$j]];
            }
        }

        $dates = [
            '2026-08-07', // Friday
            '2026-08-10', // Monday
            '2026-08-11', // Tuesday
            '2026-08-12', // Wednesday
            '2026-08-13', // Thursday
            '2026-08-14', // Friday
        ];

        $scheduleSuccessful = false;
        $finalSchedule = [];

        // Shuffle-and-retry loop to find a 100% collision-free schedule using only 19:30 and 20:30 slots
        while (!$scheduleSuccessful) {
            shuffle($matchPairs);

            $teamMatchCountPerDate = [];
            $teamSlotsPerDate = [];
            foreach ($teams as $t) {
                foreach ($dates as $d) {
                    $teamMatchCountPerDate[$t->id][$d] = 0;
                    $teamSlotsPerDate[$t->id][$d] = [];
                }
            }

            $dateMatchCount = array_fill_keys($dates, 0);
            $matchesOnDate = [];
            foreach ($dates as $d) {
                $matchesOnDate[$d] = [];
            }

            $success = true;
            $tempSchedule = [];

            foreach ($matchPairs as $pair) {
                $teamA = $pair[0];
                $teamB = $pair[1];

                $selectedDate = null;

                // Try Mon-Wed (Aug 10-12) if neither team has a match on that day
                // and the day has less than 4 matches (8 teams play, 1 rests)
                foreach (['2026-08-10', '2026-08-11', '2026-08-12'] as $d) {
                    if ($d === '2026-08-13' && ($teamA->name === 'Project VII' || $teamB->name === 'Project VII')) {
                        continue;
                    }
                    if ($dateMatchCount[$d] < 4 && $teamMatchCountPerDate[$teamA->id][$d] === 0 && $teamMatchCountPerDate[$teamB->id][$d] === 0) {
                        $selectedDate = $d;
                        break;
                    }
                }

                // Otherwise, schedule on Thursday/Friday (Aug 7, 13, 14), max 8 matches per day and max 2 matches per team
                if (!$selectedDate) {
                    $thuFriDates = ['2026-08-07', '2026-08-13', '2026-08-14'];
                    // Sort Thu/Fri dates by current load to distribute evenly
                    usort($thuFriDates, fn($a, $b) => $dateMatchCount[$a] <=> $dateMatchCount[$b]);

                    foreach ($thuFriDates as $d) {
                        if ($d === '2026-08-13' && ($teamA->name === 'Project VII' || $teamB->name === 'Project VII')) {
                            continue;
                        }
                        if ($dateMatchCount[$d] < 8 && $teamMatchCountPerDate[$teamA->id][$d] < 2 && $teamMatchCountPerDate[$teamB->id][$d] < 2) {
                            $tempMatches = $matchesOnDate[$d];
                            $tempMatches[] = [$teamA->id, $teamB->id];
                            if (self::isBipartite($tempMatches)) {
                                $selectedDate = $d;
                                break;
                            }
                        }
                    }
                }

                // Fallback: try to schedule even if it means trying other dates, but keep it 2-colorable
                if (!$selectedDate) {
                    usort($dates, fn($a, $b) => $dateMatchCount[$a] <=> $dateMatchCount[$b]);
                    foreach ($dates as $d) {
                        if ($d === '2026-08-13' && ($teamA->name === 'Project VII' || $teamB->name === 'Project VII')) {
                            continue;
                        }
                        if ($teamMatchCountPerDate[$teamA->id][$d] < 2 && $teamMatchCountPerDate[$teamB->id][$d] < 2) {
                            $tempMatches = $matchesOnDate[$d];
                            $tempMatches[] = [$teamA->id, $teamB->id];
                            if (self::isBipartite($tempMatches)) {
                                $selectedDate = $d;
                                break;
                            }
                        }
                    }
                }

                if (!$selectedDate) {
                    $success = false;
                    break; // break out of matchPairs loop, try next shuffle
                }

                // Assign time slot (find first available time slot for both teams on this date: 19:30 or 20:30)
                $timeSlots = ['19:30:00', '20:30:00'];
                $time = null;
                foreach ($timeSlots as $slot) {
                    $teamABusy = in_array($slot, $teamSlotsPerDate[$teamA->id][$selectedDate]);
                    $teamBBusy = in_array($slot, $teamSlotsPerDate[$teamB->id][$selectedDate]);
                    if (!$teamABusy && !$teamBBusy) {
                        $time = $slot;
                        break;
                    }
                }

                if ($time === null) {
                    $success = false;
                    break; // break out of matchPairs loop, try next shuffle
                }

                // Update trackers
                $teamMatchCountPerDate[$teamA->id][$selectedDate]++;
                $teamMatchCountPerDate[$teamB->id][$selectedDate]++;
                $dateMatchCount[$selectedDate]++;
                $teamSlotsPerDate[$teamA->id][$selectedDate][] = $time;
                $teamSlotsPerDate[$teamB->id][$selectedDate][] = $time;
                $matchesOnDate[$selectedDate][] = [$teamA->id, $teamB->id];

                $tempSchedule[] = [
                    'team_a' => $teamA,
                    'team_b' => $teamB,
                    'date' => $selectedDate,
                    'time' => $time
                ];
            }

            if ($success) {
                $finalSchedule = $tempSchedule;
                $scheduleSuccessful = true;
            }
        }

        // Now create the matches in database from the successful schedule
        foreach ($finalSchedule as $matchData) {
            $teamA = $matchData['team_a'];
            $teamB = $matchData['team_b'];
            $selectedDate = $matchData['date'];
            $time = $matchData['time'];

            $roundName = 'Regular Season - ' . date('d M Y', strtotime($selectedDate));

            MlMatch::create([
                'stage_id' => $regularStage->id,
                'team_a_id' => $teamA->id,
                'team_b_id' => $teamB->id,
                'best_of' => $competition->regular_season_best_of,
                'round_name' => $roundName,
                'scheduled_at' => $selectedDate . ' ' . $time,
            ]);
        }
    }

    private static function isBipartite(array $matches): bool
    {
        $n = count($matches);
        $adj = array_fill(0, $n, []);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                // If two matches share a team, they are adjacent in the conflict graph
                if (count(array_intersect($matches[$i], $matches[$j])) > 0) {
                    $adj[$i][] = $j;
                    $adj[$j][] = $i;
                }
            }
        }

        $colors = array_fill(0, $n, -1);
        for ($i = 0; $i < $n; $i++) {
            if ($colors[$i] === -1) {
                $queue = [$i];
                $colors[$i] = 0;
                $head = 0;
                while ($head < count($queue)) {
                    $u = $queue[$head++];
                    foreach ($adj[$u] as $v) {
                        if ($colors[$v] === -1) {
                            $colors[$v] = 1 - $colors[$u];
                            $queue[] = $v;
                        } elseif ($colors[$v] === $colors[$u]) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
}
