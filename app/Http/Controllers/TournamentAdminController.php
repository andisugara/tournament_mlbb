<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSetup;
use App\Models\Stage;
use App\Models\MlMatch;
use App\Models\Team;
use App\Models\Player;
use App\Models\Game;
use App\Models\PlayerGameStat;
use App\Services\BracketGeneratorService;
use App\Services\MatchPropagationService;
use App\Services\StandingsService;
use App\Services\PlayerStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TournamentAdminController extends Controller
{
    protected $standingsService;
    protected $bracketGenerator;
    protected $propagationService;
    protected $playerStatsService;

    public function __construct(
        StandingsService $standingsService,
        BracketGeneratorService $bracketGenerator,
        MatchPropagationService $propagationService,
        PlayerStatsService $playerStatsService
    ) {
        $this->standingsService = $standingsService;
        $this->bracketGenerator = $bracketGenerator;
        $this->propagationService = $propagationService;
        $this->playerStatsService = $playerStatsService;
    }

    /**
     * Show the Admin Dashboard.
     */
    public function index(): Response
    {
        $competition = CompetitionSetup::orderBy('id', 'desc')->first();
        $stages = $competition ? Stage::where('competition_id', $competition->id)->get() : collect([]);
        $regularStage = $stages->where('type', 'REGULAR_SEASON')->first();
        $playoffStage = $stages->where('type', 'PLAYOFFS')->first();

        $teams = Team::withCount('players')->get();
        $players = Player::with('team')->get();

        $matches = MlMatch::whereIn('stage_id', $stages->pluck('id'))
            ->with(['teamA', 'teamB', 'winnerTeam', 'games.playerStats.player', 'stage'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        $standings = $regularStage ? $this->standingsService->getStandings($regularStage) : [];
        $awards = $competition ? $competition->awards()->with('player.team')->get() : [];

        return Inertia::render('Dashboard', [
            'competition' => $competition,
            'stages' => $stages,
            'teams' => $teams,
            'players' => $players,
            'matches' => $matches,
            'standings' => $standings,
            'awards' => $awards,
        ]);
    }

    /**
     * Create a new Competition Setup and generate Regular Season fixtures.
     */
    public function createSetup(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'total_teams' => 'required|integer|min:4',
            'teams_advance_to_playoff' => 'required|integer|min:4',
            'upper_bracket_direct_seed' => 'required|integer|min:0',
            'regular_season_best_of' => 'required|integer|in:1,3,5,7',
            'playoff_upper_best_of' => 'required|integer|in:1,3,5,7',
            'playoff_lower_best_of' => 'required|integer|in:1,3,5,7',
            'playoff_gf_best_of' => 'required|integer|in:1,3,5,7',
            'is_double_round_robin' => 'required|boolean',
        ]);

        $T = (int) $request->teams_advance_to_playoff;
        $D = (int) $request->upper_bracket_direct_seed;

        // Custom validation rules
        if ($T > (int) $request->total_teams) {
            return back()->withErrors(['teams_advance_to_playoff' => 'Jumlah tim playoff tidak boleh melebihi total tim liga.']);
        }

        if (($T - $D) % 2 !== 0) {
            return back()->withErrors(['upper_bracket_direct_seed' => 'Selisih (Tim Playoff - Seed Langsung) harus genap untuk struktur bracket.']);
        }

        // Validate standard configs
        if (!in_array($T, [4, 6, 8]) || ($T === 4 && $D !== 0) || ($T === 6 && $D !== 2) || ($T === 8 && $D !== 0)) {
            return back()->withErrors(['teams_advance_to_playoff' => 'Sistem hanya men-support playoff standar: 4 Tim (0 seed langsung), 6 Tim (2 seed langsung), atau 8 Tim (0 seed langsung).']);
        }

        // Create setup
        $competition = CompetitionSetup::create($request->all());

        // Create stages
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

        // Generate Regular Season Round Robin Matches using existing teams
        $teams = Team::all();
        if ($teams->count() < $competition->total_teams) {
            // Fill with dummy teams if not enough in DB
            $needed = $competition->total_teams - $teams->count();
            for ($i = 1; $i <= $needed; $i++) {
                $team = Team::create(['name' => 'Dummy Team ' . $i]);
                // Create 5 dummy players
                $roles = ['gold_lane', 'exp_lane', 'mid_lane', 'jungle', 'roam'];
                foreach ($roles as $role) {
                    Player::create([
                        'team_id' => $team->id,
                        'name' => $team->name . ' Player ' . ucfirst(str_replace('_lane', '', $role)),
                        'role' => $role,
                    ]);
                }
            }
            $teams = Team::all();
        }

        // Create matches
        $matchPairs = [];
        for ($i = 0; $i < $competition->total_teams; $i++) {
            for ($j = $i + 1; $j < $competition->total_teams; $j++) {
                $matchPairs[] = [$teams[$i], $teams[$j]];
                if ($competition->is_double_round_robin) {
                    $matchPairs[] = [$teams[$j], $teams[$i]];
                }
            }
        }

        shuffle($matchPairs);
        $scheduledTime = now();

        foreach ($matchPairs as $idx => $pair) {
            MlMatch::create([
                'stage_id' => $regularStage->id,
                'team_a_id' => $pair[0]->id,
                'team_b_id' => $pair[1]->id,
                'best_of' => $competition->regular_season_best_of,
                'round_name' => 'Week ' . (int) floor($idx / 4 + 1),
                'scheduled_at' => $scheduledTime->copy()->addDays((int) floor($idx / 4))->addHours(($idx % 4) * 3),
            ]);
        }

        return redirect()->back()->with('success', 'Turnamen berhasil dibuat dan jadwal liga telah di-generate.');
    }

    /**
     * Lock standings and generate playoff bracket.
     */
    public function generatePlayoffs(): RedirectResponse
    {
        $competition = CompetitionSetup::orderBy('id', 'desc')->first();
        if (!$competition) {
            return back()->withErrors(['error' => 'Belum ada turnamen aktif.']);
        }

        $stages = Stage::where('competition_id', $competition->id)->get();
        $regularStage = $stages->where('type', 'REGULAR_SEASON')->first();
        $playoffStage = $stages->where('type', 'PLAYOFFS')->first();

        if (!$regularStage || !$playoffStage) {
            return back()->withErrors(['error' => 'Tahapan turnamen tidak lengkap.']);
        }

        // Get sorted team IDs from regular season standings
        $standings = $this->standingsService->getStandings($regularStage);
        if (count($standings) < $competition->teams_advance_to_playoff) {
            return back()->withErrors(['error' => 'Jumlah tim di klasemen kurang untuk memulai playoff.']);
        }

        $topTeamIds = collect($standings)->take($competition->teams_advance_to_playoff)->pluck('team_id')->toArray();

        // 1. Generate empty bracket
        $this->bracketGenerator->generate($playoffStage, $competition->teams_advance_to_playoff, $competition->upper_bracket_direct_seed);

        // 2. Seed the generated bracket with the top team IDs
        $this->propagationService->seedPlayoffTeams($playoffStage, $topTeamIds);

        return redirect()->back()->with('success', 'Bracket Playoff berhasil di-generate berdasarkan klasemen liga.');
    }

    /**
     * Input or edit score for a specific game within a match series.
     */
    public function storeGameScore(Request $request, MlMatch $match): RedirectResponse
    {
        $request->validate([
            'game_number' => 'required|integer',
            'winner_team_id' => 'required|integer|exists:teams,id',
            'duration_seconds' => 'nullable|integer',
            'player_stats' => 'required|array|size:10',
            'player_stats.*.player_id' => 'required|integer|exists:players,id',
            'player_stats.*.hero' => 'required|string',
            'player_stats.*.kills' => 'required|integer|min:0',
            'player_stats.*.deaths' => 'required|integer|min:0',
            'player_stats.*.assists' => 'required|integer|min:0',
            'player_stats.*.gold_earned' => 'required|integer|min:0',
            'player_stats.*.rating' => 'required|numeric|min:0|max:15',
            'player_stats.*.is_mvp' => 'required|boolean',
        ]);

        // Validate exactly one MVP
        $mvpCount = 0;
        foreach ($request->player_stats as $stat) {
            if ($stat['is_mvp']) $mvpCount++;
        }
        if ($mvpCount > 1) {
            return back()->withErrors(['player_stats' => 'Maksimal hanya boleh ada 1 MVP per game.']);
        }

        // Save game
        $game = Game::updateOrCreate(
            ['match_id' => $match->id, 'game_number' => $request->game_number],
            [
                'winner_team_id' => $request->winner_team_id,
                'duration_seconds' => $request->duration_seconds,
            ]
        );

        // Save player stats
        foreach ($request->player_stats as $stat) {
            PlayerGameStat::updateOrCreate(
                ['game_id' => $game->id, 'player_id' => $stat['player_id']],
                [
                    'hero' => $stat['hero'],
                    'kills' => $stat['kills'],
                    'deaths' => $stat['deaths'],
                    'assists' => $stat['assists'],
                    'gold_earned' => $stat['gold_earned'],
                    'rating' => $stat['rating'],
                    'is_mvp' => $stat['is_mvp'],
                ]
            );
        }

        // Trigger match propagation
        $this->propagationService->propagate($match);

        return redirect()->back()->with('success', 'Hasil game #' . $request->game_number . ' berhasil disimpan dan diperbarui.');
    }

    /**
     * Lock awards at the end of the competition.
     */
    public function lockAwards(): RedirectResponse
    {
        $competition = CompetitionSetup::orderBy('id', 'desc')->first();
        if (!$competition) {
            return back()->withErrors(['error' => 'Belum ada turnamen aktif.']);
        }

        $this->playerStatsService->lockAwards($competition->id);

        return redirect()->back()->with('success', 'Penghargaan resmi turnamen berhasil dikunci.');
    }

    /**
     * Manage Teams - Store.
     */
    public function storeTeam(Request $request): RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        Team::create($request->only('name'));
        return redirect()->back()->with('success', 'Tim berhasil ditambahkan.');
    }

    /**
     * Manage Teams - Delete.
     */
    public function deleteTeam(Team $team): RedirectResponse
    {
        $team->delete();
        return redirect()->back()->with('success', 'Tim berhasil dihapus.');
    }

    /**
     * Manage Players - Store.
     */
    public function storePlayer(Request $request): RedirectResponse
    {
        $request->validate([
            'team_id' => 'required|integer|exists:teams,id',
            'name' => 'required|string|max:255',
            'role' => 'required|string|in:gold_lane,exp_lane,mid_lane,jungle,roam',
        ]);
        Player::create($request->only('team_id', 'name', 'role'));
        return redirect()->back()->with('success', 'Pemain berhasil ditambahkan.');
    }

    /**
     * Manage Players - Delete.
     */
    public function deletePlayer(Player $player): RedirectResponse
    {
        $player->delete();
        return redirect()->back()->with('success', 'Pemain berhasil dihapus.');
    }

    /**
     * Reset the tournament (clears setups, stages, matches, games, stats, and awards).
     */
    public function resetTournament(Request $request): RedirectResponse
    {
        // Delete all competition setups - MySQL cascades delete to stages, matches, games, stats, awards
        CompetitionSetup::query()->delete();

        // Optional: delete teams and players
        if ($request->boolean('delete_teams')) {
            Team::query()->delete();
        }

        return redirect()->back()->with('success', 'Turnamen berhasil di-reset.');
    }

    /**
     * Update specific match details.
     */
    public function updateMatch(Request $request, MlMatch $match): RedirectResponse
    {
        $request->validate([
            'team_a_id' => 'nullable|integer|exists:teams,id',
            'team_b_id' => 'nullable|integer|exists:teams,id',
            'scheduled_at' => 'nullable|date',
            'round_name' => 'required|string|max:255',
            'best_of' => 'required|integer|in:1,3,5,7',
        ]);

        $match->update([
            'team_a_id' => $request->team_a_id,
            'team_b_id' => $request->team_b_id,
            'scheduled_at' => $request->scheduled_at,
            'round_name' => $request->round_name,
            'best_of' => $request->best_of,
        ]);

        // Recalculate and propagate winner downstream if changed
        $this->propagationService->propagate($match);

        return redirect()->back()->with('success', 'Detail pertandingan berhasil diperbarui.');
    }

    /**
     * Update specific team details.
     */
    public function updateTeam(Request $request, Team $team): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|string|max:2048',
        ]);

        $team->update($request->only('name', 'logo'));

        return redirect()->back()->with('success', 'Detail tim berhasil diperbarui.');
    }

    /**
     * Update specific player details.
     */
    public function updatePlayer(Request $request, Player $player): RedirectResponse
    {
        $request->validate([
            'team_id' => 'required|integer|exists:teams,id',
            'name' => 'required|string|max:255',
            'role' => 'required|string|in:gold_lane,exp_lane,mid_lane,jungle,roam',
        ]);

        $player->update($request->only('team_id', 'name', 'role'));

        return redirect()->back()->with('success', 'Data pemain berhasil diperbarui.');
    }
}
