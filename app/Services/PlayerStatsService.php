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
     * Get player statistics table data, filterable by stage ID or stage type.
     */
    public function getPlayerStatsTable(?int $stageId = null, ?string $stageType = null): array
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

            $result[] = [
                'player_id' => $player->id,
                'name' => $player->name,
                'role' => $player->role,
                'team_name' => $player->team->name,
                'team_logo' => $player->team->logo,
                'games_played' => $pStats ? (int) $pStats->games_played : 0,
                'avg_kills' => round($avgKills, 1),
                'avg_deaths' => round($avgDeaths, 1),
                'avg_assists' => round($avgAssists, 1),
                'avg_kda' => round($kda, 2),
                'avg_gold' => $pStats ? (int) round($pStats->avg_gold) : 0,
                'avg_rating' => $pStats ? round((float) $pStats->avg_rating, 2) : 0.0,
                'mvp_count' => $pStats ? (int) $pStats->mvp_count : 0,
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
            // Find average ratings for players of this role
            $players = Player::where('role', $role)
                ->with(['team'])
                ->get();

            $playerRatings = [];
            foreach ($players as $player) {
                $avgRating = PlayerGameStat::where('player_id', $player->id)->avg('rating');
                
                // Only include if they've played at least 1 game
                if ($avgRating !== null) {
                    $playerRatings[] = [
                        'player_id' => $player->id,
                        'name' => $player->name,
                        'role' => $player->role,
                        'team_name' => $player->team->name,
                        'team_logo' => $player->team->logo,
                        'avg_rating' => round((float) $avgRating, 2),
                    ];
                }
            }

            // Sort descending by rating
            usort($playerRatings, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);

            $leaderboard[$role] = $playerRatings;
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

        // 1. Calculate best player per lane
        foreach ($roles as $role) {
            $bestPlayer = Player::where('role', $role)
                ->join('player_game_stats', 'players.id', '=', 'player_game_stats.player_id')
                ->select('players.id', DB::raw('AVG(player_game_stats.rating) as avg_rating'))
                ->groupBy('players.id')
                ->orderBy('avg_rating', 'desc')
                ->first();

            if ($bestPlayer) {
                $awardType = 'best_' . str_replace('_lane', '', $role);
                // Map role names correctly to awards
                if ($role === 'jungle') $awardType = 'best_jungler';
                if ($role === 'roam') $awardType = 'best_roamer';

                $award = TournamentAward::create([
                    'competition_id' => $competitionId,
                    'award_type' => $awardType,
                    'player_id' => $bestPlayer->id,
                    'avg_rating' => $bestPlayer->avg_rating,
                ]);

                $createdAwards[] = $award->load(['player.team']);
            }
        }

        // 2. Calculate Overall MVP (highest avg rating across all lanes)
        $mvpPlayer = Player::join('player_game_stats', 'players.id', '=', 'player_game_stats.player_id')
            ->select('players.id', DB::raw('AVG(player_game_stats.rating) as avg_rating'))
            ->groupBy('players.id')
            ->orderBy('avg_rating', 'desc')
            ->first();

        if ($mvpPlayer) {
            $award = TournamentAward::create([
                'competition_id' => $competitionId,
                'award_type' => 'overall_mvp',
                'player_id' => $mvpPlayer->id,
                'avg_rating' => $mvpPlayer->avg_rating,
            ]);

            $createdAwards[] = $award->load(['player.team']);
        }

        return $createdAwards;
    }
}
