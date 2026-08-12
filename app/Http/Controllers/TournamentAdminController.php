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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
    public function index(Request $request): Response
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

        $canUseAnalyzer = $request->query('visible') === 'true';

        return Inertia::render('Dashboard', [
            'competition' => $competition,
            'stages' => $stages,
            'teams' => $teams,
            'players' => $players,
            'matches' => $matches,
            'standings' => $standings,
            'awards' => $awards,
            'canUseAnalyzer' => $canUseAnalyzer,
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
            'player_stats.*.role' => 'required|string|in:gold_lane,exp_lane,mid_lane,jungle,roam',
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
                    'role' => $stat['role'],
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
     * Process match scoreboard screenshot using Fireworks AI OCR.
     */
    public function ocrMatchScore(Request $request, MlMatch $match)
    {
        $request->validate([
            'screenshot' => 'required|image|max:10240', // max 10MB
        ]);

        $apiKey = env('FIREWORKS_API_KEY');
        $model = env('FIREWORKS_VISION_MODEL', 'accounts/fireworks/models/llama-v3p2-11b-vision-instruct');

        if (empty($apiKey)) {
            return response()->json([
                'error' => 'Fireworks API Key belum dikonfigurasi di file .env'
            ], 500);
        }

        // Get rosters for Team A and Team B
        $teamAPlayers = Player::where('team_id', $match->team_a_id)->get();
        $teamBPlayers = Player::where('team_id', $match->team_b_id)->get();

        $rosterInfo = "Roster for Team A (" . $match->teamA->name . ", ID: " . $match->team_a_id . "):\n";
        foreach ($teamAPlayers as $player) {
            $ign = trim(explode('-', $player->name)[0]);
            $rosterInfo .= "- Player ID: {$player->id}, Name in DB: '{$player->name}', IGN/In-Game Name: '{$ign}', Role: '{$player->role}'\n";
        }

        $rosterInfo .= "\nRoster for Team B (" . $match->teamB->name . ", ID: " . $match->team_b_id . "):\n";
        foreach ($teamBPlayers as $player) {
            $ign = trim(explode('-', $player->name)[0]);
            $rosterInfo .= "- Player ID: {$player->id}, Name in DB: '{$player->name}', IGN/In-Game Name: '{$ign}', Role: '{$player->role}'\n";
        }

        $file = $request->file('screenshot');
        $imagePath = $file->getRealPath();
        $mimeType = $file->getMimeType();
        $base64Image = null;

        // Try to resize image to max width 1280px to save bandwidth and speed up API response
        if (extension_loaded('gd')) {
            try {
                $imageInfo = @getimagesize($imagePath);
                if ($imageInfo) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                    $type = $imageInfo[2];
                    
                    $maxWidth = 1280;
                    if ($width > $maxWidth) {
                        $newWidth = $maxWidth;
                        $newHeight = (int)($height * ($maxWidth / $width));
                        
                        switch ($type) {
                            case IMAGETYPE_JPEG:
                                $srcImage = @imagecreatefromjpeg($imagePath);
                                break;
                            case IMAGETYPE_PNG:
                                $srcImage = @imagecreatefrompng($imagePath);
                                break;
                            case IMAGETYPE_WEBP:
                                $srcImage = @imagecreatefromwebp($imagePath);
                                break;
                            default:
                                $srcImage = null;
                        }
                        
                        if ($srcImage) {
                            $dstImage = imagecreatetruecolor($newWidth, $newHeight);
                            if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
                                imagealphablending($dstImage, false);
                                imagesavealpha($dstImage, true);
                            }
                            
                            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                            
                            ob_start();
                            if ($type === IMAGETYPE_PNG) {
                                imagepng($dstImage, null, 6);
                            } elseif ($type === IMAGETYPE_WEBP) {
                                imagewebp($dstImage, null, 80);
                            } else {
                                imagejpeg($dstImage, null, 80);
                            }
                            $imageBytes = ob_get_clean();
                            $base64Image = base64_encode($imageBytes);
                            
                            imagedestroy($srcImage);
                            imagedestroy($dstImage);
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('GD Image resize failed: ' . $e->getMessage());
            }
        }
        
        if (!$base64Image) {
            $imageBytes = file_get_contents($imagePath);
            $base64Image = base64_encode($imageBytes);
        }

        $prompt = "You are an expert Mobile Legends: Bang Bang (MLBB) match analyst.
Analyze the provided end-game screenshot (which shows the scoreboard of 10 players, 5 on Team A and 5 on Team B, with stats like KDA, Hero, Gold, Rating, and MVP).

Map the players in the screenshot to the database player IDs based on their In-Game Names (IGNs). Look at both spelling similarity and visual context.
Here are the rosters with player IDs from our database:

{$rosterInfo}

Winning Team: Determine which team won the match. The winner will be one of these IDs: {$match->team_a_id} ({$match->teamA->name}) or {$match->team_b_id} ({$match->teamB->name}). Look at the scoreboard headers (e.g. Victory / Defeat or color cues).

Match Duration: If you see the match duration (e.g. 15:32 or similar), extract it and convert it to total seconds (e.g., 15*60 + 32 = 932). If not found or unclear, default to 900.

For each of the 10 players, extract:
1. player_id (mapped from the roster above)
2. hero (English name of the hero they played, e.g. Tigreal, Pharsa, Roger, etc.)
3. kills (integer)
4. deaths (integer)
5. assists (integer)
6. rating (float/decimal rating shown in the screenshot, e.g. 10.5, 3.4. If not found, estimate based on KDA)
7. gold_earned (integer gold earned, e.g. 12000, 8500. If not shown or unclear, estimate around 10000)
8. is_mvp (boolean, set to true for EXACTLY ONE player who has the MVP or gold medal symbol on the winning team, others must be false)

Return a JSON object conforming exactly to this structure:
{
  \"winner_team_id\": <winner_team_id>,
  \"duration_seconds\": <duration_seconds>,
  \"player_stats\": [
    {
      \"player_id\": <player_id>,
      \"hero\": \"<hero_name>\",
      \"kills\": <kills>,
      \"deaths\": <deaths>,
      \"assists\": <assists>,
      \"rating\": <rating>,
      \"gold_earned\": <gold_earned>,
      \"is_mvp\": <true_or_false>
    },
    ... (exactly 10 players)
  ]
}";

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.fireworks.ai/inference/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64Image}",
                                ],
                            ],
                        ],
                    ],
                ],
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Gagal memproses gambar melalui Fireworks API: ' . $response->body()
                ], 500);
            }

            $data = $response->json();
            $messageContent = $data['choices'][0]['message']['content'] ?? null;

            if (!$messageContent) {
                return response()->json([
                    'error' => 'API tidak mengembalikan hasil pembacaan gambar.'
                ], 500);
            }

            $parsedJson = json_decode($messageContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (preg_match('/\{.*\}/s', $messageContent, $matches)) {
                    $parsedJson = json_decode($matches[0], true);
                }
            }

            if (!$parsedJson || !isset($parsedJson['player_stats'])) {
                return response()->json([
                    'error' => 'Gagal mem-parsing format hasil pembacaan AI: ' . $messageContent
                ], 500);
            }

            return response()->json($parsedJson);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
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

    public function analyzeTeams(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'team_a_id' => 'required|integer|exists:teams,id',
            'team_b_id' => 'required|integer|exists:teams,id',
        ]);

        $teamAId = $request->input('team_a_id');
        $teamBId = $request->input('team_b_id');
        $apiKey = env('FIREWORKS_API_KEY');

        if (!$apiKey) {
            return response()->json(['error' => 'FIREWORKS_API_KEY belum dikonfigurasi di file .env backend.'], 500);
        }

        if ($teamAId == $teamBId) {
            return response()->json(['error' => 'Tim kiri dan tim kanan tidak boleh sama.'], 400);
        }

        try {
            $teamA = Team::findOrFail($teamAId);
            $teamB = Team::findOrFail($teamBId);

            $teamAPlayers = Player::where('team_id', $teamAId)->get();
            $teamBPlayers = Player::where('team_id', $teamBId)->get();

            $allPlayerStats = collect($this->playerStatsService->getPlayerStatsTable())->keyBy('player_id');
            $playerIds = $teamAPlayers->pluck('id')->merge($teamBPlayers->pluck('id'))->toArray();

            $laneSwaps = DB::table('player_game_stats')
                ->select('player_id', 'role', DB::raw('COUNT(*) as count'))
                ->whereIn('player_id', $playerIds)
                ->groupBy('player_id', 'role')
                ->get()
                ->groupBy('player_id');

            // Compile Team A string
            $teamAData = "";
            foreach ($teamAPlayers as $p) {
                $stats = $allPlayerStats->get($p->id);
                $swaps = $laneSwaps->get($p->id);
                $swapStr = "";
                if ($swaps) {
                    $swapParts = [];
                    foreach ($swaps as $s) {
                        $swapParts[] = ($s->role ?: $p->role) . " (" . $s->count . "x)";
                    }
                    $swapStr = implode(", ", $swapParts);
                } else {
                    $swapStr = "Belum main";
                }

                $heroPoolStr = "";
                if ($stats && isset($stats['hero_pool_details'])) {
                    $hParts = [];
                    foreach ($stats['hero_pool_details'] as $h) {
                        $hParts[] = $h['hero'] . " (" . $h['count'] . "x)";
                    }
                    $heroPoolStr = implode(", ", $hParts);
                } else {
                    $heroPoolStr = "-";
                }

                $teamAData .= "- **{$p->name}** (Registered: {$p->role}):\n";
                $teamAData .= "  * Games Played: " . ($stats['games_played'] ?? 0) . "\n";
                $teamAData .= "  * Avg Rating: " . ($stats['avg_rating'] ?? 0) . " | KDA: " . ($stats['avg_kda'] ?? 0) . " | MVP: " . ($stats['mvp_count'] ?? 0) . "\n";
                $teamAData .= "  * Roles Played in Games: {$swapStr}\n";
                $teamAData .= "  * Hero Pool: {$heroPoolStr}\n\n";
            }

            // Compile Team B string
            $teamBData = "";
            foreach ($teamBPlayers as $p) {
                $stats = $allPlayerStats->get($p->id);
                $swaps = $laneSwaps->get($p->id);
                $swapStr = "";
                if ($swaps) {
                    $swapParts = [];
                    foreach ($swaps as $s) {
                        $swapParts[] = ($s->role ?: $p->role) . " (" . $s->count . "x)";
                    }
                    $swapStr = implode(", ", $swapParts);
                } else {
                    $swapStr = "Belum main";
                }

                $heroPoolStr = "";
                if ($stats && isset($stats['hero_pool_details'])) {
                    $hParts = [];
                    foreach ($stats['hero_pool_details'] as $h) {
                        $hParts[] = $h['hero'] . " (" . $h['count'] . "x)";
                    }
                    $heroPoolStr = implode(", ", $hParts);
                } else {
                    $heroPoolStr = "-";
                }

                $teamBData .= "- **{$p->name}** (Registered: {$p->role}):\n";
                $teamBData .= "  * Games Played: " . ($stats['games_played'] ?? 0) . "\n";
                $teamBData .= "  * Avg Rating: " . ($stats['avg_rating'] ?? 0) . " | KDA: " . ($stats['avg_kda'] ?? 0) . " | MVP: " . ($stats['mvp_count'] ?? 0) . "\n";
                $teamBData .= "  * Roles Played in Games: {$swapStr}\n";
                $teamBData .= "  * Hero Pool: {$heroPoolStr}\n\n";
            }

            $prompt = "Kamu adalah analis, pelatih, dan strategist profesional esports Mobile Legends: Bang Bang (MLBB) kelas dunia (MPL).\n";
            $prompt .= "Tugas utama Anda adalah membuat **analisis taktis mendalam** agar **Tim A ({$teamA->name})** bisa **mengalahkan** **Tim B ({$teamB->name})**.\n\n";
            $prompt .= "Berikut adalah data statistik, roster, pool hero, dan histori lane swap untuk kedua tim:\n\n";
            $prompt .= "=== DATA TIM A ({$teamA->name} - Target Menang) ===\n";
            $prompt .= $teamAData . "\n";
            $prompt .= "=== DATA TIM B ({$teamB->name} - Lawan yang Harus Dikalahkan) ===\n";
            $prompt .= $teamBData . "\n";
            $prompt .= "Tulislah laporan analisis taktis dalam Bahasa Indonesia yang berfokus penuh pada hal-hal berikut:\n\n";
            $prompt .= "1. **KEKUATAN UTAMA TIM B (Lawan)**: Siapa pemain paling berbahaya di Tim B berdasarkan rating/KDA? Bagaimana gaya permainan mereka dan hero apa saja yang sering mereka pick?\n";
            $prompt .= "2. **REKOMENDASI BAN UNTUK TIM A**: Berdasarkan statistik hero pool Tim B di atas, sebutkan minimal 3-5 hero andalan pemain Tim B yang **wajib di-BAN** oleh Tim A beserta alasan taktisnya.\n";
            $prompt .= "3. **REKOMENDASI PICK & COUNTER-DRAFT UNTUK TIM A**: Hero apa saja yang harus diamankan Tim A untuk meng-counter hero andalan Tim B? Berikan rekomendasi pick berdasarkan hero pool pemain Tim A.\n";
            $prompt .= "4. **POLA SWAP LANE & PERMAINAN**: Tunjukkan jika ada pemain Tim B yang sering bertukar lane/role (berdasarkan data *Roles Played in Games*). Bagaimana Tim A harus mengantisipasi rotasi ini?\n";
            $prompt .= "5. **KUNCI KEMENANGAN (WIN CONDITION)**: Rencana taktis step-by-step in-game (Early Game, Mid Game, Late Game) untuk menumbangkan Tim B.\n\n";
            $prompt .= "Catatan: Jangan memotong analisis Anda di tengah jalan. Tulislah secara lengkap, padat, dan gunakan format Markdown (tabel, poin, bold) agar mudah dibaca oleh pelatih/pemain.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.fireworks.ai/inference/v1/chat/completions', [
                'model' => env('FIREWORKS_CHAT_MODEL', 'accounts/fireworks/models/deepseek-v4-flash-0731'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 4096
            ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'API Fireworks error: ' . $response->body()
                ], $response->status());
            }

            $resJson = $response->json();
            $analysis = $resJson['choices'][0]['message']['content'] ?? 'Tidak ada hasil analisis.';

            return response()->json([
                'analysis' => $analysis
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }
}
