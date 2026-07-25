<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Team;
use App\Models\Stage;
use App\Models\MlMatch;
use App\Models\Game;
use App\Models\Player;
use App\Models\CompetitionSetup;
use App\Services\BracketGeneratorService;
use App\Services\MatchPropagationService;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TournamentServicesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Double Elimination Bracket Generation.
     */
    public function test_bracket_generation_for_6_teams()
    {
        $competition = CompetitionSetup::create([
            'name' => 'Test Playoff',
            'total_teams' => 6,
            'teams_advance_to_playoff' => 6,
            'upper_bracket_direct_seed' => 2,
        ]);

        $stage = Stage::create([
            'competition_id' => $competition->id,
            'type' => 'PLAYOFFS',
            'format' => 'DOUBLE_ELIMINATION',
        ]);

        $generator = new BracketGeneratorService();
        $matches = $generator->generate($stage, 6, 2, 3); // T=6, D=2, BO3

        // A standard 6-team double elimination bracket has exactly 8 matches (including grand final)
        $this->assertCount(8, $matches);

        // Check if Grand Final match exists and has BO7
        $gf = MlMatch::where('stage_id', $stage->id)->where('match_code', 'GF_M1')->first();
        $this->assertNotNull($gf);
        $this->assertEquals(7, $gf->best_of);

        // Check if upper bracket round 1 has seeding configured correctly
        $ubR1M1 = MlMatch::where('stage_id', $stage->id)->where('match_code', 'UB_R1_M1')->first();
        $this->assertNotNull($ubR1M1);
        $this->assertEquals('Upper Bracket Round 1', $ubR1M1->round_name);
    }

    /**
     * Test Match Winner Propagation.
     */
    public function test_match_propagation_advances_winner_and_loser()
    {
        // Create 2 teams
        $teamA = Team::create(['name' => 'Team A']);
        $teamB = Team::create(['name' => 'Team B']);

        $competition = CompetitionSetup::create([
            'name' => 'Test Bracket Propagation',
            'total_teams' => 4,
            'teams_advance_to_playoff' => 4,
            'upper_bracket_direct_seed' => 0,
        ]);

        $stage = Stage::create([
            'competition_id' => $competition->id,
            'type' => 'PLAYOFFS',
            'format' => 'DOUBLE_ELIMINATION',
        ]);

        // Generate bracket (T=4, D=0)
        $generator = new BracketGeneratorService();
        $generator->generate($stage, 4, 0, 3); // BO3

        // Let's seed UB_R1_M1 with Team A and Team B
        $match = MlMatch::where('stage_id', $stage->id)->where('match_code', 'UB_R1_M1')->first();
        $match->team_a_id = $teamA->id;
        $match->team_b_id = $teamB->id;
        $match->save();

        // Feed results: Game 1 won by Team A, Game 2 won by Team A. Team A wins 2-0 (majority in BO3)
        Game::create([
            'match_id' => $match->id,
            'game_number' => 1,
            'winner_team_id' => $teamA->id,
        ]);

        $game2 = Game::create([
            'match_id' => $match->id,
            'game_number' => 2,
            'winner_team_id' => $teamA->id,
        ]);

        // Trigger propagation
        $propagationService = new MatchPropagationService();
        $propagationService->propagate($match);

        // Match should have winner set to Team A
        $match->refresh();
        $this->assertEquals($teamA->id, $match->winner_team_id);

        // Check if winner went to UB_R2_M1 (Upper Bracket Final)
        $targetWinMatch = MlMatch::where('stage_id', $stage->id)->where('match_code', 'UB_R2_M1')->first();
        $this->assertEquals($teamA->id, $targetWinMatch->team_a_id);

        // Check if loser went to LB_R1_M1 (Lower Bracket Semis)
        $targetLoseMatch = MlMatch::where('stage_id', $stage->id)->where('match_code', 'LB_R1_M1')->first();
        $this->assertEquals($teamB->id, $targetLoseMatch->team_a_id);
    }

    /**
     * Test tournament reset deletes settings and setups.
     */
    public function test_tournament_reset_clears_configs_but_optionally_preserves_teams()
    {
        // Setup a competition
        $team = Team::create(['name' => 'Team Alpha']);
        $competition = CompetitionSetup::create([
            'name' => 'Test Tournament To Reset',
            'total_teams' => 4,
            'teams_advance_to_playoff' => 4,
            'upper_bracket_direct_seed' => 0,
            'regular_season_best_of' => 3,
            'playoff_upper_best_of' => 3,
            'playoff_lower_best_of' => 3,
            'playoff_gf_best_of' => 7,
        ]);

        $stage = Stage::create([
            'competition_id' => $competition->id,
            'type' => 'REGULAR_SEASON',
            'format' => 'ROUND_ROBIN',
        ]);

        // Post to reset route via controller action or directly
        $controller = new \App\Http\Controllers\TournamentAdminController(
            new \App\Services\StandingsService(),
            new \App\Services\BracketGeneratorService(),
            new \App\Services\MatchPropagationService(),
            new \App\Services\PlayerStatsService()
        );

        // Reset without deleting teams
        $request = new \Illuminate\Http\Request(['delete_teams' => false]);
        $response = $controller->resetTournament($request);

        $this->assertEquals(0, CompetitionSetup::count());
        $this->assertEquals(0, Stage::count());
        $this->assertEquals(1, Team::count()); // Team preserved!

        // Setup again
        $competition = CompetitionSetup::create([
            'name' => 'Test Tournament To Reset Part 2',
            'total_teams' => 4,
            'teams_advance_to_playoff' => 4,
            'upper_bracket_direct_seed' => 0,
            'regular_season_best_of' => 3,
            'playoff_upper_best_of' => 3,
            'playoff_lower_best_of' => 3,
            'playoff_gf_best_of' => 7,
        ]);

        // Reset with deleting teams
        $request = new \Illuminate\Http\Request(['delete_teams' => true]);
        $controller->resetTournament($request);

        $this->assertEquals(0, CompetitionSetup::count());
        $this->assertEquals(0, Team::count()); // Team deleted!
    }

    /**
     * Test match detail update modifications.
     */
    public function test_match_details_update_modifies_schedule_and_team_and_bo()
    {
        $teamA = Team::create(['name' => 'Team A']);
        $teamB = Team::create(['name' => 'Team B']);
        $teamC = Team::create(['name' => 'Team C']);

        $competition = CompetitionSetup::create([
            'name' => 'Test Match Update',
            'total_teams' => 4,
            'teams_advance_to_playoff' => 4,
            'upper_bracket_direct_seed' => 0,
            'regular_season_best_of' => 3,
            'playoff_upper_best_of' => 3,
            'playoff_lower_best_of' => 3,
            'playoff_gf_best_of' => 7,
        ]);

        $stage = Stage::create([
            'competition_id' => $competition->id,
            'type' => 'REGULAR_SEASON',
            'format' => 'ROUND_ROBIN',
        ]);

        $match = MlMatch::create([
            'stage_id' => $stage->id,
            'team_a_id' => $teamA->id,
            'team_b_id' => $teamB->id,
            'best_of' => 3,
            'round_name' => 'Week 1',
        ]);

        $controller = new \App\Http\Controllers\TournamentAdminController(
            new \App\Services\StandingsService(),
            new \App\Services\BracketGeneratorService(),
            new \App\Services\MatchPropagationService(),
            new \App\Services\PlayerStatsService()
        );

        $request = new \Illuminate\Http\Request([
            'team_a_id' => $teamA->id,
            'team_b_id' => $teamC->id, // Swapped Team B with Team C
            'scheduled_at' => '2026-07-25 18:00:00',
            'round_name' => 'Week 1 Modified',
            'best_of' => 5, // Upgraded to BO5
        ]);

        $controller->updateMatch($request, $match);

        $match->refresh();
        $this->assertEquals($teamC->id, $match->team_b_id);
        $this->assertEquals('Week 1 Modified', $match->round_name);
        $this->assertEquals(5, $match->best_of);
        $this->assertEquals('2026-07-25 18:00:00', $match->scheduled_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test double round robin match generation.
     */
    public function test_double_round_robin_match_generation()
    {
        // Setup 4 teams
        $teams = [];
        for ($i = 1; $i <= 4; $i++) {
            $teams[] = Team::create(['name' => 'Team ' . $i]);
        }

        // Setup competition with double round robin enabled
        $controller = new \App\Http\Controllers\TournamentAdminController(
            new \App\Services\StandingsService(),
            new \App\Services\BracketGeneratorService(),
            new \App\Services\MatchPropagationService(),
            new \App\Services\PlayerStatsService()
        );

        $request = new \Illuminate\Http\Request([
            'name' => 'Double Round Robin League',
            'total_teams' => 4,
            'teams_advance_to_playoff' => 4,
            'upper_bracket_direct_seed' => 0,
            'regular_season_best_of' => 3,
            'playoff_upper_best_of' => 3,
            'playoff_lower_best_of' => 3,
            'playoff_gf_best_of' => 7,
            'is_double_round_robin' => true, // Enabled!
        ]);

        $controller->createSetup($request);

        // A single round robin with 4 teams produces 6 matches.
        // A double round robin must produce 12 matches.
        $competition = CompetitionSetup::orderBy('id', 'desc')->first();
        $regularStage = Stage::where('competition_id', $competition->id)->where('type', 'REGULAR_SEASON')->first();
        $matchesCount = MlMatch::where('stage_id', $regularStage->id)->count();

        $this->assertEquals(12, $matchesCount);
    }

    /**
     * Test team and player update actions.
     */
    public function test_team_and_player_update_actions()
    {
        $team = Team::create(['name' => 'EVOS Esports', 'logo' => 'evos.png']);
        $player = Player::create([
            'team_id' => $team->id,
            'name' => 'Wannn',
            'role' => 'jungle',
        ]);

        $controller = new \App\Http\Controllers\TournamentAdminController(
            new \App\Services\StandingsService(),
            new \App\Services\BracketGeneratorService(),
            new \App\Services\MatchPropagationService(),
            new \App\Services\PlayerStatsService()
        );

        // 1. Update Team
        $requestTeam = new \Illuminate\Http\Request([
            'name' => 'EVOS Legends',
            'logo' => 'evos_legends.png',
        ]);
        $controller->updateTeam($requestTeam, $team);

        $team->refresh();
        $this->assertEquals('EVOS Legends', $team->name);
        $this->assertEquals('evos_legends.png', $team->logo);

        // 2. Update Player
        $requestPlayer = new \Illuminate\Http\Request([
            'team_id' => $team->id,
            'name' => 'Wannn MVP',
            'role' => 'mid_lane',
        ]);
        $controller->updatePlayer($requestPlayer, $player);

        $player->refresh();
        $this->assertEquals('Wannn MVP', $player->name);
        $this->assertEquals('mid_lane', $player->role);
    }
}
