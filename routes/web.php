<?php

use App\Http\Controllers\Admin\ExtraPollingStationController;
use App\Http\Controllers\Admin\FamilyAssignmentController;
use App\Http\Controllers\Admin\ImportVotersController;
use App\Http\Controllers\Admin\LocalPollingStationController;
use App\Http\Controllers\Admin\OrgGraphController;
use App\Http\Controllers\Admin\ResultsController;
use App\Http\Controllers\Admin\Settings\BelongsController;
use App\Http\Controllers\Admin\Settings\GendersController;
use App\Http\Controllers\Admin\Settings\ProfessionsController;
use App\Http\Controllers\Admin\Settings\RolesController;
use App\Http\Controllers\Admin\Settings\SectsController;
use App\Http\Controllers\Admin\StationAssignmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserInvitationController;
use App\Http\Controllers\Api\BallotController;
use App\Http\Controllers\Api\StationResultsController;
use App\Http\Controllers\BallotEntryController;
use App\Http\Controllers\DashboardController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// Wrapped broadcasting auth route with light logging to help debug private channel auth.
Route::post('broadcasting/auth', function (Request $request) {
    try {
        Log::info('Broadcasting auth attempt', [
            'ip' => $request->ip(),
            'has_cookie' => ! empty($request->cookies->all()),
            'cookie_names' => array_keys($request->cookies->all()),
            'content_length' => $request->header('content-length'),
            'user_id' => optional($request->user())->getKey(),
        ]);
    } catch (\Throwable $e) {
        // swallow logging errors
    }

    return app(BroadcastController::class)->authenticate($request);
})->middleware(['auth']);

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
        Route::get('results', [ResultsController::class, 'index'])->name('results.index');
        Route::get('results/data', [ResultsController::class, 'data'])->name('results.data');
        // Import voters (CSV) UI and action
        Route::get('import-voters', [ImportVotersController::class, 'index'])->name('import-voters.index');
        Route::post('import-voters', [ImportVotersController::class, 'store'])->name('import-voters.store');
        Route::delete('import-voters/{filename}', [ImportVotersController::class, 'destroy'])->name('import-voters.destroy');
        Route::get('import-voters/download/{filename}', [ImportVotersController::class, 'download'])->name('import-voters.download');
        Route::post('import-voters/{upload}/clean', [ImportVotersController::class, 'clean'])->name('import-voters.clean');
        Route::post('import-voters/{upload}/import', [ImportVotersController::class, 'import'])->name('import-voters.import');
        Route::get('import-voters/{upload}/report', [ImportVotersController::class, 'report'])->name('import-voters.report');
        // Import batch review and soft-delete actions
        Route::get('import-batches/{batch}', [ImportVotersController::class, 'showImportBatch'])->name('import-batches.show');
        Route::post('import-batches/{batch}/soft-delete', [ImportVotersController::class, 'softDelete'])->name('import-batches.soft_delete');

        // Family Assignment
        Route::get('family-assignment', [FamilyAssignmentController::class, 'index'])->name('family-assignment.index');
        Route::post('family-assignment/assign', [FamilyAssignmentController::class, 'assign'])->name('family-assignment.assign');
        Route::post('family-assignment/assign-all', [FamilyAssignmentController::class, 'assignAll'])->name('family-assignment.assign-all');

        // Organization Graph
        Route::get('org-graph', [OrgGraphController::class, 'index'])->name('org-graph.index');
        Route::post('branch/{branch}/towns', [OrgGraphController::class, 'attachTown'])->name('branch.towns.attach');
        Route::delete('branch/{branch}/towns/{town}', [OrgGraphController::class, 'detachTown'])->name('branch.towns.detach');
        Route::get('delegate/{delegate}/district-towns', [OrgGraphController::class, 'delegateDistrictTowns'])->name('delegate.district.towns');
        // External Polling Stations CRUD
        Route::resource('extra-polling-stations', ExtraPollingStationController::class);
        // Local Polling Stations (in-town) CRUD
        Route::get('local-polling-stations/{local_polling_station}/payload', [LocalPollingStationController::class, 'payload'])->name('local-polling-stations.payload');
        Route::resource('local-polling-stations', LocalPollingStationController::class);

        // Admin Settings CRUD (roles, belongs, professions, genders, sects)
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [RolesController::class, 'create'])->name('roles.create');
            Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RolesController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RolesController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');

            Route::resource('belongs', BelongsController::class)->except(['show']);
            Route::resource('professions', ProfessionsController::class)->except(['show']);
            Route::resource('genders', GendersController::class)->except(['show']);
            Route::resource('sects', SectsController::class)->except(['show']);
        });
    });

    // Ballot Entry API Routes
    Route::prefix('api/stations')->name('api.stations.')->group(function () {
        Route::post('{station}/ballots', [BallotController::class, 'store'])->name('ballots.store');
        Route::get('{station}/ballots', [BallotController::class, 'index'])->name('ballots.index');
        Route::get('{station}/results', [StationResultsController::class, 'show'])->name('results.show');
        Route::get('{station}/results/summary', [StationResultsController::class, 'summary'])->name('results.summary');
        Route::get('{station}/results/aggregates', [StationResultsController::class, 'aggregates'])->name('results.aggregates');
    });
});

require __DIR__.'/settings.php';
