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
            'is_double_round_robin' => true,
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

        // 6. Generate Round Robin Matches
        $matchPairs = [];
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $matchPairs[] = [$teams[$i], $teams[$j]]; // Home
                $matchPairs[] = [$teams[$j], $teams[$i]]; // Away
            }
        }

        // Shuffle pairs to distribute matches naturally
        shuffle($matchPairs);

        $dates = [
            '2026-08-07', // Friday
            '2026-08-10', // Monday
            '2026-08-11', // Tuesday
            '2026-08-12', // Wednesday
            '2026-08-13', // Thursday
            '2026-08-14', // Friday
        ];

        // Track matches per team per date
        $teamMatchCountPerDate = [];
        foreach ($teams as $t) {
            foreach ($dates as $d) {
                $teamMatchCountPerDate[$t->id][$d] = 0;
            }
        }

        // Track total matches per date
        $dateMatchCount = array_fill_keys($dates, 0);

        foreach ($matchPairs as $idx => $pair) {
            $teamA = $pair[0];
            $teamB = $pair[1];

            $selectedDate = null;

            // Try to schedule in Mon-Wed (Aug 10-12) if neither team has a match on that day
            // and the day has less than 4 matches (8 teams play, 1 rests)
            foreach (['2026-08-10', '2026-08-11', '2026-08-12'] as $d) {
                if ($dateMatchCount[$d] < 4 && $teamMatchCountPerDate[$teamA->id][$d] === 0 && $teamMatchCountPerDate[$teamB->id][$d] === 0) {
                    $selectedDate = $d;
                    break;
                }
            }

            // Otherwise, schedule on Thursday/Friday (Aug 7, 13, 14)
            // Select the date with the fewest matches scheduled so far
            if (!$selectedDate) {
                $thuFriDates = ['2026-08-07', '2026-08-13', '2026-08-14'];
                usort($thuFriDates, fn($a, $b) => $dateMatchCount[$a] <=> $dateMatchCount[$b]);
                $selectedDate = $thuFriDates[0];
            }

            // Update trackers
            $teamMatchCountPerDate[$teamA->id][$selectedDate]++;
            $teamMatchCountPerDate[$teamB->id][$selectedDate]++;
            $dateMatchCount[$selectedDate]++;

            // Determine round name based on date
            $roundName = 'Regular Season - ' . date('d M Y', strtotime($selectedDate));

            MlMatch::create([
                'stage_id' => $regularStage->id,
                'team_a_id' => $teamA->id,
                'team_b_id' => $teamB->id,
                'best_of' => $competition->regular_season_best_of,
                'round_name' => $roundName,
                'scheduled_at' => $selectedDate . ' 19:30:00',
            ]);
        }
    }
}
