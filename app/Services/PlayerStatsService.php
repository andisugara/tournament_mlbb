<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerGameStat;
use App\Models\TournamentAward;
use App\Models\CompetitionSetup;
use Illuminate\Support\Facades\DB;

class PlayerStatsService
{
    /**
     * Get player statistics table data, filterable by stage ID, stage type, or competition ID.
     */
    public function getPlayerStatsTable(?int $stageId = null, ?string $stageType = null, ?int $competitionId = null): array
    {
        // 1. Fetch base player stats query
        $statsQuery = PlayerGameStat::query()
            ->select('player_game_stats.player_id')
            ->selectRaw('COUNT(player_game_stats.id) as games_played')
            ->selectRaw('AVG(player_game_stats.kills) as avg_kills')
            ->selectRaw('SUM(player_game_stats.kills) as total_kills')
            ->selectRaw('AVG(player_game_stats.deaths) as avg_deaths')
            ->selectRaw('SUM(player_game_stats.deaths) as total_deaths')
            ->selectRaw('AVG(player_game_stats.assists) as avg_assists')
            ->selectRaw('SUM(player_game_stats.assists) as total_assists')
            ->selectRaw('AVG(player_game_stats.gold_earned) as avg_gold')
            ->selectRaw('AVG(player_game_stats.rating) as avg_rating')
            ->selectRaw('SUM(CASE WHEN player_game_stats.is_mvp = 1 THEN 1 ELSE 0 END) as mvp_count')
            ->join('games', 'player_game_stats.game_id', '=', 'games.id')
            ->join('matches', 'games.match_id', '=', 'matches.id')
            ->join('stages', 'matches.stage_id', '=', 'stages.id');

        // Apply filters
        if ($stageId) {
            $statsQuery->where('matches.stage_id', $stageId);
        } elseif ($stageType) {
            $statsQuery->where('stages.type', $stageType);
        }

        if ($competitionId) {
            $statsQuery->where('stages.competition_id', $competitionId);
        }

        $aggregatedStats = $statsQuery->groupBy('player_game_stats.player_id')->get()->keyBy('player_id');

        // 2. Fetch hero counts to find the most played hero per player
        $heroCountsQuery = PlayerGameStat::query()
            ->select('player_game_stats.player_id', 'player_game_stats.hero')
            ->selectRaw('COUNT(*) as play_count')
            ->join('games', 'player_game_stats.game_id', '=', 'games.id')
            ->join('matches', 'games.match_id', '=', 'matches.id')
            ->join('stages', 'matches.stage_id', '=', 'stages.id');

        if ($stageId) {
            $heroCountsQuery->where('matches.stage_id', $stageId);
        } elseif ($stageType) {
            $heroCountsQuery->where('stages.type', $stageType);
        }

        if ($competitionId) {
            $heroCountsQuery->where('stages.competition_id', $competitionId);
        }

        $heroCounts = $heroCountsQuery->groupBy('player_game_stats.player_id', 'player_game_stats.hero')
            ->orderBy('play_count', 'desc')
            ->get()
            ->groupBy('player_id');

        // 3. Fetch all players and merge their data
        $players = Player::with(['team'])->get();
        $result = [];

        foreach ($players as $player) {
            $pStats = $aggregatedStats->get($player->id);
            $pHeroes = $heroCounts->get($player->id);
            $mostPlayedHero = $pHeroes && $pHeroes->first() ? $pHeroes->first()->hero : '-';

            // KDA calculation: (Kills + Assists) / Deaths (if Deaths = 0, K+A is KDA)
            $avgKills = $pStats ? (float) $pStats->avg_kills : 0.0;
            $avgDeaths = $pStats ? (float) $pStats->avg_deaths : 0.0;
            $avgAssists = $pStats ? (float) $pStats->avg_assists : 0.0;
            $kda = $avgDeaths > 0 ? ($avgKills + $avgAssists) / $avgDeaths : ($avgKills + $avgAssists);

            // Weighted Rating Calculation:
            // (0.4 * Avg In-Game Rating) + (0.25 * KDA) + (0.15 * (Avg Gold / 1000)) + (2.0 * MVP Rate)
            $avgRating = $pStats ? (float) $pStats->avg_rating : 0.0;
            $avgGold = $pStats ? (float) $pStats->avg_gold : 0.0;
            $mvpCount = $pStats ? (int) $pStats->mvp_count : 0;
            $gamesPlayed = $pStats ? (int) $pStats->games_played : 0;
            $mvpRate = $gamesPlayed > 0 ? $mvpCount / $gamesPlayed : 0.0;

            if ($gamesPlayed > 0) {
                $weightedRating = (0.4 * $avgRating) 
                                + (0.25 * $kda) 
                                + (0.15 * ($avgGold / 1000.0)) 
                                + (2.0 * $mvpRate);
            } else {
                $weightedRating = 0.0;
            }

            $result[] = [
                'player_id' => $player->id,
                'name' => $player->name,
                'role' => $player->role,
                'team_name' => $player->team->name,
                'team_logo' => $player->team->logo,
                'games_played' => $gamesPlayed,
                'avg_kills' => round($avgKills, 1),
                'avg_deaths' => round($avgDeaths, 1),
                'avg_assists' => round($avgAssists, 1),
                'avg_kda' => round($kda, 2),
                'avg_gold' => (int) round($avgGold),
                'avg_rating' => round($weightedRating, 2),
                'mvp_count' => $mvpCount,
                'most_played_hero' => $mostPlayedHero,
            ];
        }

        return $result;
    }

    /**
     * Get the current leaderboard (running) per lane based on all matches.
     */
    public function getLeaderboard(): array
    {
        $roles = ['gold_lane', 'exp_lane', 'mid_lane', 'jungle', 'roam'];
        $leaderboard = [];

        foreach ($roles as $role) {
            $leaderboard[$role] = [];
        }

        // Fetch all player statistics (calculates custom weighted rating)
        $allStats = $this->getPlayerStatsTable();

        foreach ($allStats as $stat) {
            if ($stat['games_played'] > 0 && isset($leaderboard[$stat['role']])) {
                $leaderboard[$stat['role']][] = [
                    'player_id' => $stat['player_id'],
                    'name' => $stat['name'],
                    'role' => $stat['role'],
                    'team_name' => $stat['team_name'],
                    'team_logo' => $stat['team_logo'],
                    'avg_rating' => $stat['avg_rating'],
                ];
            }
        }

        // Sort descending by rating
        foreach ($roles as $role) {
            usort($leaderboard[$role], fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);
        }

        return $leaderboard;
    }

    /**
     * Lock and save official tournament awards.
     */
    public function lockAwards(int $competitionId): array
    {
        // Delete any existing awards for this competition
        TournamentAward::where('competition_id', $competitionId)->delete();

        $roles = ['gold_lane', 'exp_lane', 'mid_lane', 'jungle', 'roam'];
        $createdAwards = [];

        // Fetch stats for all players in this competition (calculates custom weighted rating)
        $allStats = $this->getPlayerStatsTable(null, null, $competitionId);

        // 1. Calculate best player per lane
        foreach ($roles as $role) {
            $roleStats = array_filter($allStats, fn($stat) => $stat['role'] === $role && $stat['games_played'] > 0);

            if (!empty($roleStats)) {
                // Sort descending by rating
                usort($roleStats, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);
                $bestPlayerStat = $roleStats[0];

                $awardType = 'best_' . str_replace('_lane', '', $role);
                // Map role names correctly to awards
                if ($role === 'jungle') $awardType = 'best_jungler';
                if ($role === 'roam') $awardType = 'best_roamer';

                $award = TournamentAward::create([
                    'competition_id' => $competitionId,
                    'award_type' => $awardType,
                    'player_id' => $bestPlayerStat['player_id'],
                    'avg_rating' => $bestPlayerStat['avg_rating'],
                ]);

                $createdAwards[] = $award->load(['player.team']);
            }
        }

        // 2. Calculate Overall MVP (highest avg rating across all lanes)
        $activeStats = array_filter($allStats, fn($stat) => $stat['games_played'] > 0);

        if (!empty($activeStats)) {
            // Sort descending by rating
            usort($activeStats, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);
            $mvpPlayerStat = $activeStats[0];

            $award = TournamentAward::create([
                'competition_id' => $competitionId,
                'award_type' => 'overall_mvp',
                'player_id' => $mvpPlayerStat['player_id'],
                'avg_rating' => $mvpPlayerStat['avg_rating'],
            ]);

            $createdAwards[] = $award->load(['player.team']);
        }

        return $createdAwards;
    }
}
