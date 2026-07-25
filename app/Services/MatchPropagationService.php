<?php

namespace App\Services;

use App\Models\Game;
use App\Models\MlMatch;
use App\Models\Stage;
use Exception;

class MatchPropagationService
{
    /**
     * Re-calculate matches and propagate winners/losers.
     */
    public function propagate(MlMatch $match): void
    {
        $teamA = $match->team_a_id;
        $teamB = $match->team_b_id;

        if (!$teamA || !$teamB) {
            // Can't determine a winner of a match if both teams are not set
            return;
        }

        // Count game wins for each team
        $games = $match->games()->whereNotNull('winner_team_id')->get();
        $winsA = $games->where('winner_team_id', $teamA)->count();
        $winsB = $games->where('winner_team_id', $teamB)->count();

        $neededToWin = (int) floor($match->best_of / 2) + 1;

        $winnerId = null;
        $loserId = null;

        if ($winsA >= $neededToWin) {
            $winnerId = $teamA;
            $loserId = $teamB;
        } elseif ($winsB >= $neededToWin) {
            $winnerId = $teamB;
            $loserId = $teamA;
        }

        // Update match winner if decided, otherwise nullify if games deleted/changed
        $match->winner_team_id = $winnerId;
        $match->save();

        if ($winnerId && $loserId) {
            // Propagate Winner
            if ($match->feeds_win_to_match_id) {
                $targetMatch = MlMatch::find($match->feeds_win_to_match_id);
                if ($targetMatch) {
                    if ($match->feeds_win_to_slot === 'team_a') {
                        $targetMatch->team_a_id = $winnerId;
                    } else {
                        $targetMatch->team_b_id = $winnerId;
                    }
                    $targetMatch->save();
                    
                    // Recursively propagate target if it was already marked as winner previously
                    $this->propagate($targetMatch);
                }
            }

            // Propagate Loser
            if ($match->feeds_lose_to_match_id) {
                $targetMatch = MlMatch::find($match->feeds_lose_to_match_id);
                if ($targetMatch) {
                    if ($match->feeds_lose_to_slot === 'team_a') {
                        $targetMatch->team_a_id = $loserId;
                    } else {
                        $targetMatch->team_b_id = $loserId;
                    }
                    $targetMatch->save();
                    
                    // Recursively propagate target if it was already marked as winner previously
                    $this->propagate($targetMatch);
                }
            }
        } else {
            // If winner was unset, clear downstream propagation
            $this->clearDownstream($match);
        }
    }

    /**
     * Clear downstream teams if a winner is revoked.
     */
    protected function clearDownstream(MlMatch $match): void
    {
        if ($match->feeds_win_to_match_id) {
            $target = MlMatch::find($match->feeds_win_to_match_id);
            if ($target) {
                if ($match->feeds_win_to_slot === 'team_a') {
                    $target->team_a_id = null;
                } else {
                    $target->team_b_id = null;
                }
                $target->winner_team_id = null;
                $target->save();
                $this->clearDownstream($target);
            }
        }

        if ($match->feeds_lose_to_match_id) {
            $target = MlMatch::find($match->feeds_lose_to_match_id);
            if ($target) {
                if ($match->feeds_lose_to_slot === 'team_a') {
                    $target->team_a_id = null;
                } else {
                    $target->team_b_id = null;
                }
                $target->winner_team_id = null;
                $target->save();
                $this->clearDownstream($target);
            }
        }
    }

    /**
     * Seed the initial playoff bracket matches using standings teams sorted by rank.
     * $sortedTeamIds contains team IDs sorted in order of rank (rank 1 at index 0, rank 2 at index 1, etc.)
     */
    public function seedPlayoffTeams(Stage $playoffStage, array $sortedTeamIds): void
    {
        // Fetch all matches for this playoff stage that have team_a_seed or team_b_seed configured in the templates
        $matches = MlMatch::where('stage_id', $playoffStage->id)->get();

        // Get template mapping to read seed requirements
        $stageType = $playoffStage->competitionSetup->teams_advance_to_playoff;
        $directSeeds = $playoffStage->competitionSetup->upper_bracket_direct_seed;

        $generator = new BracketGeneratorService();
        $template = $generator->getTemplate($stageType, $directSeeds);

        foreach ($matches as $match) {
            // Find the matching template item
            $def = collect($template)->firstWhere('match_code', $match->match_code);
            if (!$def) continue;

            $updated = false;

            // Seed Team A
            if (isset($def['team_a_seed'])) {
                $seedIdx = $def['team_a_seed'] - 1; // 1-indexed seed to 0-indexed array
                if (isset($sortedTeamIds[$seedIdx])) {
                    $match->team_a_id = $sortedTeamIds[$seedIdx];
                    $updated = true;
                }
            }

            // Seed Team B
            if (isset($def['team_b_seed'])) {
                $seedIdx = $def['team_b_seed'] - 1;
                if (isset($sortedTeamIds[$seedIdx])) {
                    $match->team_b_id = $sortedTeamIds[$seedIdx];
                    $updated = true;
                }
            }

            if ($updated) {
                $match->save();
                // Check if match propagation is possible immediately (e.g. if one team is direct winner, though unlikely at seed time)
                $this->propagate($match);
            }
        }
    }
}
