<?php

namespace App\Services;

use App\Models\Stage;
use App\Models\Team;
use App\Models\MlMatch;

class StandingsService
{
    /**
     * Calculate standings for a regular season stage.
     */
    public function getStandings(Stage $stage): array
    {
        $competition = $stage->competitionSetup;
        
        // Fetch all teams
        $teams = Team::all();

        // Initialize standings structure
        $standings = [];
        foreach ($teams as $team) {
            $standings[$team->id] = [
                'team_id' => $team->id,
                'name' => $team->name,
                'logo' => $team->logo,
                'played' => 0,
                'wins' => 0,
                'losses' => 0,
                'games_won' => 0,
                'games_lost' => 0,
                'net_games' => 0,
                'points' => 0, // Match wins = points
            ];
        }

        // Fetch completed matches in this stage
        $matches = MlMatch::where('stage_id', $stage->id)
            ->whereNotNull('winner_team_id')
            ->with(['games'])
            ->get();

        // Keep track of pairwise match outcomes for head-to-head tiebreaking
        // Format: $h2hMatrix[teamA_id][teamB_id] = number of times teamA beat teamB
        $h2hMatrix = [];

        foreach ($matches as $match) {
            $teamA = $match->team_a_id;
            $teamB = $match->team_b_id;
            $winner = $match->winner_team_id;

            if (!isset($standings[$teamA]) || !isset($standings[$teamB])) {
                continue;
            }

            // Update Matches Played
            $standings[$teamA]['played']++;
            $standings[$teamB]['played']++;

            // Update Match Wins/Losses
            if ($winner == $teamA) {
                $standings[$teamA]['wins']++;
                $standings[$teamA]['points']++;
                $standings[$teamB]['losses']++;
                
                $h2hMatrix[$teamA][$teamB] = ($h2hMatrix[$teamA][$teamB] ?? 0) + 1;
            } else {
                $standings[$teamB]['wins']++;
                $standings[$teamB]['points']++;
                $standings[$teamA]['losses']++;

                $h2hMatrix[$teamB][$teamA] = ($h2hMatrix[$teamB][$teamA] ?? 0) + 1;
            }

            // Update Game stats
            foreach ($match->games as $game) {
                if ($game->winner_team_id == $teamA) {
                    $standings[$teamA]['games_won']++;
                    $standings[$teamB]['games_lost']++;
                } elseif ($game->winner_team_id == $teamB) {
                    $standings[$teamB]['games_won']++;
                    $standings[$teamA]['games_lost']++;
                }
            }
        }

        // Calculate Net Games for all teams
        foreach ($standings as $id => $stats) {
            $standings[$id]['net_games'] = $stats['games_won'] - $stats['games_lost'];
        }

        // Sort using tiebreak rules
        $standingsArray = array_values($standings);

        usort($standingsArray, function ($a, $b) use ($h2hMatrix) {
            // Rule 1: Match Wins (points)
            if ($b['wins'] !== $a['wins']) {
                return $b['wins'] - $a['wins'];
            }

            // Rule 2: Head-to-Head
            $aBeatB = $h2hMatrix[$a['team_id']][$b['team_id']] ?? 0;
            $bBeatA = $h2hMatrix[$b['team_id']][$a['team_id']] ?? 0;
            if ($aBeatB !== $bBeatA) {
                return $bBeatA - $aBeatB; // Less wins = higher index = ranked lower. So if B beat A, B should be placed higher (return positive index difference)
            }

            // Rule 3: Net Game Win
            if ($b['net_games'] !== $a['net_games']) {
                return $b['net_games'] - $a['net_games'];
            }

            // Rule 4: Games Won
            if ($b['games_won'] !== $a['games_won']) {
                return $b['games_won'] - $a['games_won'];
            }

            // Fallback: Name
            return strcmp($a['name'], $b['name']);
        });

        // Add rank numbering
        foreach ($standingsArray as $index => $data) {
            $standingsArray[$index]['rank'] = $index + 1;
        }

        return $standingsArray;
    }
}
