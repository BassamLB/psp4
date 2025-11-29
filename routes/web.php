<?php

use App\Http\Controllers\Admin\StationAssignmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserInvitationController;
use App\Http\Controllers\BallotEntryController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Broadcast::routes(['middleware' => ['auth']]);

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified', 'user.allowed'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Ballot Entry
    Route::get('ballots/entry/{station}', [BallotEntryController::class, 'show'])->name('ballots.entry');
    Route::get('ballots/entry-grid/{station}', [BallotEntryController::class, 'showGrid'])->name('ballots.entry.grid');

    // Admin routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        // User management
        Route::resource('users', UserController::class);
        Route::post('users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
        Route::post('users/{user}/block', [UserController::class, 'block'])->name('users.block');
        Route::post('users/{user}/unblock', [UserController::class, 'unblock'])->name('users.unblock');

        // User invitations
        Route::get('invitations', [UserInvitationController::class, 'index'])->name('invitations.index');
        Route::get('invitations/create', [UserInvitationController::class, 'create'])->name('invitations.create');
        Route::post('invitations', [UserInvitationController::class, 'store'])->name('invitations.store');

        // Station assignments
        Route::get('station-assignments', [StationAssignmentController::class, 'index'])->name('station-assignments.index');
        Route::get('station-assignments/create', [StationAssignmentController::class, 'create'])->name('station-assignments.create');
        Route::post('station-assignments', [StationAssignmentController::class, 'store'])->name('station-assignments.store');
        Route::post('station-assignments/{assignment}/toggle', [StationAssignmentController::class, 'toggleStatus'])->name('station-assignments.toggle');
        Route::delete('station-assignments/{assignment}', [StationAssignmentController::class, 'destroy'])->name('station-assignments.destroy');

        // Admin Results / Drilldown
        Route::get('results', [App\Http\Controllers\Admin\ResultsController::class, 'index'])->name('results.index');
        Route::get('results/data', [App\Http\Controllers\Admin\ResultsController::class, 'data'])->name('results.data');
        // Organization Graph
        Route::get('org-graph', [App\Http\Controllers\Admin\OrgGraphController::class, 'index'])->name('org-graph.index');
        Route::post('branch/{branch}/towns', [App\Http\Controllers\Admin\OrgGraphController::class, 'attachTown'])->name('branch.towns.attach');
        Route::delete('branch/{branch}/towns/{town}', [App\Http\Controllers\Admin\OrgGraphController::class, 'detachTown'])->name('branch.towns.detach');
        Route::get('delegate/{delegate}/district-towns', [App\Http\Controllers\Admin\OrgGraphController::class, 'delegateDistrictTowns'])->name('delegate.district.towns');
        // External Polling Stations CRUD
        Route::resource('polling-stations', App\Http\Controllers\Admin\PollingStationController::class);
    });

    // Ballot Entry API Routes
    Route::prefix('api/stations')->name('api.stations.')->group(function () {
        Route::post('{station}/ballots', [App\Http\Controllers\Api\BallotController::class, 'store'])->name('ballots.store');
        Route::get('{station}/ballots', [App\Http\Controllers\Api\BallotController::class, 'index'])->name('ballots.index');
        Route::get('{station}/results', [App\Http\Controllers\Api\StationResultsController::class, 'show'])->name('results.show');
        Route::get('{station}/results/summary', [App\Http\Controllers\Api\StationResultsController::class, 'summary'])->name('results.summary');
        Route::get('{station}/results/aggregates', [App\Http\Controllers\Api\StationResultsController::class, 'aggregates'])->name('results.aggregates');
    });
});

require __DIR__.'/settings.php';
