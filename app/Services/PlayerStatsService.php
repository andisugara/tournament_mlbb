<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerGameStat;
use App\Models\TournamentAward;
use Illuminate\Support\Facades\DB;

class PlayerStatsService
{
    /**
     * Get player statistics table data, filterable by stage ID, stage type, or competition ID.
     */
    public function getPlayerStatsTable(?int $stageId = null, ?string $stageType = null, ?int $competitionId = null): array
    {
        // 1. Fetch individual player game stats with duration
        $statsQuery = PlayerGameStat::query()
            ->select('player_game_stats.*', 'games.duration_seconds')
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

        $rawStats = $statsQuery->get();
        $groupedStats = $rawStats->groupBy('player_id');

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
            $pStats = $groupedStats->get($player->id);
            $pHeroes = $heroCounts->get($player->id);
            $mostPlayedHero = $pHeroes && $pHeroes->first() ? $pHeroes->first()->hero : '-';

            if ($pStats && $pStats->count() > 0) {
                $gamesPlayed = $pStats->count();
                
                // Sum and average calculations
                $totalKills = $pStats->sum('kills');
                $totalDeaths = $pStats->sum('deaths');
                $totalAssists = $pStats->sum('assists');

                $avgKills = $pStats->avg('kills');
                $avgDeaths = $pStats->avg('deaths');
                $avgAssists = $pStats->avg('assists');
                $avgGold = $pStats->avg('gold_earned');

                $kda = $totalDeaths > 0 ? ($totalKills + $totalAssists) / $totalDeaths : ($totalKills + $totalAssists);
                $mvpCount = $pStats->where('is_mvp', true)->count();
                $mvpRate = $mvpCount / $gamesPlayed;

                // Hero Pool Versatility Bonus: min(0.5, (unique_heroes - 1) * 0.1)
                $uniqueHeroesCount = $pStats->pluck('hero')->unique()->count();
                $heroPoolBonus = min(0.5, ($uniqueHeroesCount - 1) * 0.1);

                // Get all heroes and count their occurrences
                $heroCountsMap = $pStats->groupBy('hero')->map(fn($group) => $group->count())->toArray();
                arsort($heroCountsMap);
                $heroPoolDetails = [];
                foreach ($heroCountsMap as $heroName => $count) {
                    $heroPoolDetails[] = [
                        'hero' => $heroName,
                        'count' => $count,
                    ];
                }

                // Compute GPM and Rating without penalty (Opsi 2)
                $totalRating = 0.0;
                $totalGpm = 0.0;

                foreach ($pStats as $stat) {
                    $totalRating += (float) $stat->rating;

                    // Calculate GPM (Gold Per Minute)
                    $durationSeconds = $stat->duration_seconds ?: 900;
                    $durationMinutes = $durationSeconds / 60.0;
                    $gpm = $stat->gold_earned / $durationMinutes;
                    $totalGpm += $gpm;
                }

                $avgRating = $totalRating / $gamesPlayed;
                $avgGpm = $totalGpm / $gamesPlayed;
                $gpmScore = $avgGpm / 100.0;

                // Weighted Rating Formula:
                // (0.4 * Avg Rating) + (0.25 * KDA) + (0.15 * GPM Score) + (2.0 * MVP Rate) + Hero Pool Bonus
                $weightedRating = (0.4 * $avgRating) 
                                + (0.25 * $kda) 
                                + (0.15 * $gpmScore) 
                                + (2.0 * $mvpRate) 
                                + $heroPoolBonus;
            } else {
                $gamesPlayed = 0;
                $avgKills = 0.0;
                $avgDeaths = 0.0;
                $avgAssists = 0.0;
                $kda = 0.0;
                $avgGold = 0.0;
                $weightedRating = 0.0;
                $mvpCount = 0;
                $uniqueHeroesCount = 0;
                $heroPoolDetails = [];
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
                'hero_pool_size' => $uniqueHeroesCount,
                'hero_pool_details' => $heroPoolDetails,
            ];
        }

        return $result;
    }

    /**
     * Get player statistics grouped by the role actually played in the game.
     */
    public function getPlayerStatsGroupedByRole(?int $competitionId = null): array
    {
        $statsQuery = PlayerGameStat::query()
            ->select('player_game_stats.*', 'games.duration_seconds')
            ->join('games', 'player_game_stats.game_id', '=', 'games.id')
            ->join('matches', 'games.match_id', '=', 'matches.id')
            ->join('stages', 'matches.stage_id', '=', 'stages.id');

        if ($competitionId) {
            $statsQuery->where('stages.competition_id', $competitionId);
        }

        $rawStats = $statsQuery->get();
        
        // Group by player_id to get total games per player
        $playerTotalGames = $rawStats->groupBy('player_id')->map(fn($group) => $group->count());

        // Group by player_id and played role
        $grouped = $rawStats->groupBy(function($item) {
            return $item->player_id . ':' . ($item->role ?: 'roam');
        });

        $players = Player::with(['team'])->get()->keyBy('id');
        $result = [];

        foreach ($grouped as $key => $pStats) {
            list($playerId, $playedRole) = explode(':', $key);
            $player = $players->get($playerId);
            if (!$player) continue;

            $gamesPlayed = $pStats->count();
            $totalPlayerGames = $playerTotalGames->get($playerId) ?: 1;
            $roleRatio = $gamesPlayed / $totalPlayerGames;

            $totalKills = $pStats->sum('kills');
            $totalDeaths = $pStats->sum('deaths');
            $totalAssists = $pStats->sum('assists');

            $avgKills = $pStats->avg('kills');
            $avgDeaths = $pStats->avg('deaths');
            $avgAssists = $pStats->avg('assists');
            $avgGold = $pStats->avg('gold_earned');

            $kda = $totalDeaths > 0 ? ($totalKills + $totalAssists) / $totalDeaths : ($totalKills + $totalAssists);
            $mvpCount = $pStats->where('is_mvp', true)->count();
            $mvpRate = $mvpCount / $gamesPlayed;

            $uniqueHeroesCount = $pStats->pluck('hero')->unique()->count();
            $heroPoolBonus = min(0.5, ($uniqueHeroesCount - 1) * 0.1);

            $totalRating = 0.0;
            $totalGpm = 0.0;

            foreach ($pStats as $stat) {
                $totalRating += (float) $stat->rating;
                $durationSeconds = $stat->duration_seconds ?: 900;
                $gpm = $stat->gold_earned / ($durationSeconds / 60.0);
                $totalGpm += $gpm;
            }

            $avgRating = $totalRating / $gamesPlayed;
            $avgGpm = $totalGpm / $gamesPlayed;
            $gpmScore = $avgGpm / 100.0;

            $weightedRating = (0.4 * $avgRating) 
                            + (0.25 * $kda) 
                            + (0.15 * $gpmScore) 
                            + (2.0 * $mvpRate) 
                            + $heroPoolBonus;

            $finalRoleRating = $weightedRating * $roleRatio;

            $result[] = [
                'player_id' => (int) $playerId,
                'name' => $player->name,
                'role' => $playedRole,
                'team_name' => $player->team->name,
                'team_logo' => $player->team->logo,
                'games_played' => $gamesPlayed,
                'avg_rating' => round($finalRoleRating, 2),
                'avg_kda' => round($kda, 2),
                'avg_gold' => (int) round($avgGold),
                'mvp_count' => $mvpCount,
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

        // Fetch stats grouped by player and role
        $allRoleStats = $this->getPlayerStatsGroupedByRole();

        foreach ($allRoleStats as $stat) {
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

        // Fetch stats grouped by player and role for this competition
        $allRoleStats = $this->getPlayerStatsGroupedByRole($competitionId);

        // 1. Calculate best player per lane
        foreach ($roles as $role) {
            $roleStats = array_filter($allRoleStats, fn($stat) => $stat['role'] === $role && $stat['games_played'] > 0);

            if (!empty($roleStats)) {
                // Sort descending by rating
                usort($roleStats, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);
                $bestPlayerStat = reset($roleStats);

                $awardType = 'best_' . str_replace('_lane', '', $role);
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

        // 2. Calculate Overall MVP (highest avg rating across all lanes based on OVERALL stats)
        $allOverallStats = $this->getPlayerStatsTable(null, null, $competitionId);
        $activeOverallStats = array_filter($allOverallStats, fn($stat) => $stat['games_played'] > 0);

        if (!empty($activeOverallStats)) {
            usort($activeOverallStats, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);
            $mvpPlayerStat = reset($activeOverallStats);

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
