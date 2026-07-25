<?php

namespace App\Services;

use App\Models\Stage;
use App\Models\MlMatch;
use Exception;

class BracketGeneratorService
{
    /**
     * Get the template for a given playoff configuration.
     */
    public function getTemplate(int $teams, int $directSeeds): array
    {
        if ($teams === 4 && $directSeeds === 0) {
            return [
                [
                    'match_code' => 'UB_R1_M1',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Semifinals',
                    'team_a_seed' => 1,
                    'team_b_seed' => 4,
                    'feeds_win_to_match_code' => 'UB_R2_M1',
                    'feeds_win_to_slot' => 'team_a',
                    'feeds_lose_to_match_code' => 'LB_R1_M1',
                    'feeds_lose_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'UB_R1_M2',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Semifinals',
                    'team_a_seed' => 2,
                    'team_b_seed' => 3,
                    'feeds_win_to_match_code' => 'UB_R2_M1',
                    'feeds_win_to_slot' => 'team_b',
                    'feeds_lose_to_match_code' => 'LB_R1_M1',
                    'feeds_lose_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'LB_R1_M1',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Semifinals',
                    'team_a_source_match_code' => 'UB_R1_M1',
                    'team_a_source_type' => 'LOSER',
                    'team_b_source_match_code' => 'UB_R1_M2',
                    'team_b_source_type' => 'LOSER',
                    'feeds_win_to_match_code' => 'LB_R2_M1',
                    'feeds_win_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'UB_R2_M1',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Final',
                    'team_a_source_match_code' => 'UB_R1_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'UB_R1_M2',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'GF_M1',
                    'feeds_win_to_slot' => 'team_a',
                    'feeds_lose_to_match_code' => 'LB_R2_M1',
                    'feeds_lose_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'LB_R2_M1',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Final',
                    'team_a_source_match_code' => 'LB_R1_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'UB_R2_M1',
                    'team_b_source_type' => 'LOSER',
                    'feeds_win_to_match_code' => 'GF_M1',
                    'feeds_win_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'GF_M1',
                    'bracket_type' => 'GRAND_FINAL',
                    'round_name' => 'Grand Final',
                    'team_a_source_match_code' => 'UB_R2_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'LB_R2_M1',
                    'team_b_source_type' => 'WINNER',
                ],
            ];
        }

        if ($teams === 6 && $directSeeds === 2) {
            return [
                [
                    'match_code' => 'UB_R1_M1',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Round 1',
                    'team_a_seed' => 3,
                    'team_b_seed' => 6,
                    'feeds_win_to_match_code' => 'UB_R2_M2',
                    'feeds_win_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'UB_R1_M2',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Round 1',
                    'team_a_seed' => 4,
                    'team_b_seed' => 5,
                    'feeds_win_to_match_code' => 'UB_R2_M1',
                    'feeds_win_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'UB_R2_M1',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Semifinals',
                    'team_a_seed' => 1,
                    'team_b_source_match_code' => 'UB_R1_M2',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'UB_R3_M1',
                    'feeds_win_to_slot' => 'team_a',
                    'feeds_lose_to_match_code' => 'LB_R1_M1',
                    'feeds_lose_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'UB_R2_M2',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Semifinals',
                    'team_a_seed' => 2,
                    'team_b_source_match_code' => 'UB_R1_M1',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'UB_R3_M1',
                    'feeds_win_to_slot' => 'team_b',
                    'feeds_lose_to_match_code' => 'LB_R1_M1',
                    'feeds_lose_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'LB_R1_M1',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Semifinals',
                    'team_a_source_match_code' => 'UB_R2_M1',
                    'team_a_source_type' => 'LOSER',
                    'team_b_source_match_code' => 'UB_R2_M2',
                    'team_b_source_type' => 'LOSER',
                    'feeds_win_to_match_code' => 'LB_R2_M1',
                    'feeds_win_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'UB_R3_M1',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Final',
                    'team_a_source_match_code' => 'UB_R2_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'UB_R2_M2',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'GF_M1',
                    'feeds_win_to_slot' => 'team_a',
                    'feeds_lose_to_match_code' => 'LB_R2_M1',
                    'feeds_lose_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'LB_R2_M1',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Final',
                    'team_a_source_match_code' => 'LB_R1_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'UB_R3_M1',
                    'team_b_source_type' => 'LOSER',
                    'feeds_win_to_match_code' => 'GF_M1',
                    'feeds_win_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'GF_M1',
                    'bracket_type' => 'GRAND_FINAL',
                    'round_name' => 'Grand Final',
                    'team_a_source_match_code' => 'UB_R3_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'LB_R2_M1',
                    'team_b_source_type' => 'WINNER',
                ],
            ];
        }

        if ($teams === 8 && $directSeeds === 0) {
            return [
                [
                    'match_code' => 'UB_R1_M1',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Quarterfinals',
                    'team_a_seed' => 1,
                    'team_b_seed' => 8,
                    'feeds_win_to_match_code' => 'UB_R2_M1',
                    'feeds_win_to_slot' => 'team_a',
                    'feeds_lose_to_match_code' => 'LB_R1_M1',
                    'feeds_lose_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'UB_R1_M2',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Quarterfinals',
                    'team_a_seed' => 4,
                    'team_b_seed' => 5,
                    'feeds_win_to_match_code' => 'UB_R2_M1',
                    'feeds_win_to_slot' => 'team_b',
                    'feeds_lose_to_match_code' => 'LB_R1_M1',
                    'feeds_lose_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'UB_R1_M3',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Quarterfinals',
                    'team_a_seed' => 2,
                    'team_b_seed' => 7,
                    'feeds_win_to_match_code' => 'UB_R2_M2',
                    'feeds_win_to_slot' => 'team_a',
                    'feeds_lose_to_match_code' => 'LB_R1_M2',
                    'feeds_lose_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'UB_R1_M4',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Quarterfinals',
                    'team_a_seed' => 3,
                    'team_b_seed' => 6,
                    'feeds_win_to_match_code' => 'UB_R2_M2',
                    'feeds_win_to_slot' => 'team_b',
                    'feeds_lose_to_match_code' => 'LB_R1_M2',
                    'feeds_lose_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'LB_R1_M1',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Round 1',
                    'team_a_source_match_code' => 'UB_R1_M1',
                    'team_a_source_type' => 'LOSER',
                    'team_b_source_match_code' => 'UB_R1_M2',
                    'team_b_source_type' => 'LOSER',
                    'feeds_win_to_match_code' => 'LB_R2_M1',
                    'feeds_win_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'LB_R1_M2',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Round 1',
                    'team_a_source_match_code' => 'UB_R1_M3',
                    'team_a_source_type' => 'LOSER',
                    'team_b_source_match_code' => 'UB_R1_M4',
                    'team_b_source_type' => 'LOSER',
                    'feeds_win_to_match_code' => 'LB_R2_M2',
                    'feeds_win_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'UB_R2_M1',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Semifinals',
                    'team_a_source_match_code' => 'UB_R1_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'UB_R1_M2',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'UB_R3_M1',
                    'feeds_win_to_slot' => 'team_a',
                    'feeds_lose_to_match_code' => 'LB_R2_M2',
                    'feeds_lose_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'UB_R2_M2',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Semifinals',
                    'team_a_source_match_code' => 'UB_R1_M3',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'UB_R1_M4',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'UB_R3_M1',
                    'feeds_win_to_slot' => 'team_b',
                    'feeds_lose_to_match_code' => 'LB_R2_M1',
                    'feeds_lose_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'LB_R2_M1',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Round 2',
                    'team_a_source_match_code' => 'UB_R2_M2',
                    'team_a_source_type' => 'LOSER',
                    'team_b_source_match_code' => 'LB_R1_M1',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'LB_R3_M1',
                    'feeds_win_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'LB_R2_M2',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Round 2',
                    'team_a_source_match_code' => 'UB_R2_M1',
                    'team_a_source_type' => 'LOSER',
                    'team_b_source_match_code' => 'LB_R1_M2',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'LB_R3_M1',
                    'feeds_win_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'LB_R3_M1',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Semifinals',
                    'team_a_source_match_code' => 'LB_R2_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'LB_R2_M2',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'LB_R4_M1',
                    'feeds_win_to_slot' => 'team_a',
                ],
                [
                    'match_code' => 'UB_R3_M1',
                    'bracket_type' => 'UPPER',
                    'round_name' => 'Upper Bracket Final',
                    'team_a_source_match_code' => 'UB_R2_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'UB_R2_M2',
                    'team_b_source_type' => 'WINNER',
                    'feeds_win_to_match_code' => 'GF_M1',
                    'feeds_win_to_slot' => 'team_a',
                    'feeds_lose_to_match_code' => 'LB_R4_M1',
                    'feeds_lose_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'LB_R4_M1',
                    'bracket_type' => 'LOWER',
                    'round_name' => 'Lower Bracket Final',
                    'team_a_source_match_code' => 'LB_R3_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'UB_R3_M1',
                    'team_b_source_type' => 'LOSER',
                    'feeds_win_to_match_code' => 'GF_M1',
                    'feeds_win_to_slot' => 'team_b',
                ],
                [
                    'match_code' => 'GF_M1',
                    'bracket_type' => 'GRAND_FINAL',
                    'round_name' => 'Grand Final',
                    'team_a_source_match_code' => 'UB_R3_M1',
                    'team_a_source_type' => 'WINNER',
                    'team_b_source_match_code' => 'LB_R4_M1',
                    'team_b_source_type' => 'WINNER',
                ],
            ];
        }

        throw new Exception("Konfigurasi Playoff (T: $teams, D: $directSeeds) tidak didukung. Mohon gunakan konfigurasi standar (T=4/D=0, T=6/D=2, T=8/D=0).");
    }

    /**
     * Generate the playoff bracket matches.
     */
    public function generate(Stage $stage, int $teams, int $directSeeds): array
    {
        $template = $this->getTemplate($teams, $directSeeds);
        
        $competition = $stage->competitionSetup;
        $upperBo = $competition ? $competition->playoff_upper_best_of : 3;
        $lowerBo = $competition ? $competition->playoff_lower_best_of : 3;
        $gfBo = $competition ? $competition->playoff_gf_best_of : 7;

        // Delete existing matches for this stage to avoid duplication
        MlMatch::where('stage_id', $stage->id)->delete();

        $createdMatches = [];

        // Pass 1: Create matches with basic fields
        foreach ($template as $matchDef) {
            $bo = $upperBo;
            if ($matchDef['bracket_type'] === 'LOWER') {
                $bo = $lowerBo;
            } elseif ($matchDef['bracket_type'] === 'GRAND_FINAL') {
                $bo = $gfBo;
            }

            $match = MlMatch::create([
                'stage_id' => $stage->id,
                'bracket_type' => $matchDef['bracket_type'],
                'round_name' => $matchDef['round_name'],
                'match_code' => $matchDef['match_code'],
                'best_of' => $bo,
                'team_a_id' => null,
                'team_b_id' => null,
                'winner_team_id' => null,
            ]);

            $createdMatches[$matchDef['match_code']] = $match;
        }

        // Pass 2: Set relation mappings & feeds connections
        foreach ($template as $matchDef) {
            $match = $createdMatches[$matchDef['match_code']];

            $updateData = [];

            // Feeds Win
            if (isset($matchDef['feeds_win_to_match_code'])) {
                $targetCode = $matchDef['feeds_win_to_match_code'];
                $updateData['feeds_win_to_match_id'] = $createdMatches[$targetCode]->id;
                $updateData['feeds_win_to_slot'] = $matchDef['feeds_win_to_slot'];
            }

            // Feeds Lose
            if (isset($matchDef['feeds_lose_to_match_code'])) {
                $targetCode = $matchDef['feeds_lose_to_match_code'];
                $updateData['feeds_lose_to_match_id'] = $createdMatches[$targetCode]->id;
                $updateData['feeds_lose_to_slot'] = $matchDef['feeds_lose_to_slot'];
            }

            // Source A
            if (isset($matchDef['team_a_source_match_code'])) {
                $srcCode = $matchDef['team_a_source_match_code'];
                $updateData['team_a_source_match_id'] = $createdMatches[$srcCode]->id;
                $updateData['team_a_source_type'] = $matchDef['team_a_source_type'];
            }

            // Source B
            if (isset($matchDef['team_b_source_match_code'])) {
                $srcCode = $matchDef['team_b_source_match_code'];
                $updateData['team_b_source_match_id'] = $createdMatches[$srcCode]->id;
                $updateData['team_b_source_type'] = $matchDef['team_b_source_type'];
            }

            if (!empty($updateData)) {
                $match->update($updateData);
            }
        }

        return array_values($createdMatches);
    }
}
