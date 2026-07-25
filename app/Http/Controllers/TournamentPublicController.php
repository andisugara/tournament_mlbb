<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSetup;
use App\Models\Stage;
use App\Models\MlMatch;
use App\Models\TournamentAward;
use App\Services\StandingsService;
use App\Services\PlayerStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TournamentPublicController extends Controller
{
    protected $standingsService;
    protected $playerStatsService;

    public function __construct(StandingsService $standingsService, PlayerStatsService $playerStatsService)
    {
        $this->standingsService = $standingsService;
        $this->playerStatsService = $playerStatsService;
    }

    /**
     * Display the public tournament landing page.
     */
    public function index(Request $request): Response
    {
        // 1. Fetch current competition
        $competition = CompetitionSetup::orderBy('id', 'desc')->first();

        if (!$competition) {
            return Inertia::render('Welcome', [
                'competition' => null,
                'stages' => [],
                'standings' => [],
                'matches' => [],
                'leaderboard' => [],
                'playerStats' => [],
                'awards' => [],
            ]);
        }

        // 2. Fetch stages
        $stages = Stage::where('competition_id', $competition->id)->get();
        $regularStage = $stages->where('type', 'REGULAR_SEASON')->first();
        $playoffStage = $stages->where('type', 'PLAYOFFS')->first();

        // 3. Compute Standings (Regular Season)
        $standings = $regularStage ? $this->standingsService->getStandings($regularStage) : [];

        // 4. Fetch all matches with team details and game scores
        $matches = MlMatch::whereIn('stage_id', $stages->pluck('id'))
            ->with(['teamA', 'teamB', 'winnerTeam', 'games', 'stage'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        // 5. Fetch current best player per lane leaderboard (aggregated)
        $leaderboard = $this->playerStatsService->getLeaderboard();

        // 6. Fetch player stats table based on filter (stage_type or stage_id)
        $stageType = $request->query('stage_type', 'REGULAR_SEASON'); // default regular
        $stageId = $request->query('stage_id') ? (int) $request->query('stage_id') : null;

        $playerStats = $this->playerStatsService->getPlayerStatsTable($stageId, $stageType);

        // 7. Fetch official awards if locked
        $awards = TournamentAward::where('competition_id', $competition->id)
            ->with(['player.team'])
            ->get();

        return Inertia::render('Welcome', [
            'competition' => $competition,
            'stages' => $stages,
            'standings' => $standings,
            'matches' => $matches,
            'leaderboard' => $leaderboard,
            'playerStats' => $playerStats,
            'awards' => $awards,
            'filters' => [
                'stage_type' => $stageType,
                'stage_id' => $stageId,
            ],
        ]);
    }
}
