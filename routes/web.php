<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\TournamentPublicController;
use App\Http\Controllers\TournamentAdminController;

Route::get('/', [TournamentPublicController::class, 'index'])->name('public.index');

Route::get('/dashboard', [TournamentAdminController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin tournament control routes
    Route::post('/admin/setup', [TournamentAdminController::class, 'createSetup'])->name('admin.setup');
    Route::post('/admin/generate-playoffs', [TournamentAdminController::class, 'generatePlayoffs'])->name('admin.generate-playoffs');
    Route::post('/admin/matches/{match}/game', [TournamentAdminController::class, 'storeGameScore'])->name('admin.game.store');
    Route::post('/admin/matches/{match}/ocr-score', [TournamentAdminController::class, 'ocrMatchScore'])->name('admin.game.ocr');
    Route::post('/admin/lock-awards', [TournamentAdminController::class, 'lockAwards'])->name('admin.lock-awards');
    Route::post('/admin/reset', [TournamentAdminController::class, 'resetTournament'])->name('admin.reset');
    Route::patch('/admin/matches/{match}', [TournamentAdminController::class, 'updateMatch'])->name('admin.matches.update');
    
    // CRUD teams & players
    Route::post('/admin/teams', [TournamentAdminController::class, 'storeTeam'])->name('admin.teams.store');
    Route::patch('/admin/teams/{team}', [TournamentAdminController::class, 'updateTeam'])->name('admin.teams.update');
    Route::delete('/admin/teams/{team}', [TournamentAdminController::class, 'deleteTeam'])->name('admin.teams.destroy');
    Route::post('/admin/players', [TournamentAdminController::class, 'storePlayer'])->name('admin.players.store');
    Route::patch('/admin/players/{player}', [TournamentAdminController::class, 'updatePlayer'])->name('admin.players.update');
    Route::delete('/admin/players/{player}', [TournamentAdminController::class, 'deletePlayer'])->name('admin.players.destroy');
});

require __DIR__.'/auth.php';
