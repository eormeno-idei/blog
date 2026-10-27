<?php

use App\Http\Controllers\LogViewerController;
use Idei\Usim\Support\UsimConfig;
use Illuminate\Support\Facades\Route;

if (app()->environment('local', 'testing')) {
    // Rutas para el visor de logs - Solo en entorno local
    Route::prefix('logs')->group(function () {
        Route::get('/', [LogViewerController::class, 'index'])->name('logs.index');
        Route::get('/content', [LogViewerController::class, 'content'])->name('logs.content');
        Route::get('/download', [LogViewerController::class, 'download'])->name('logs.download');
        Route::post('/clear', [LogViewerController::class, 'clear'])->name('logs.clear');
    });
}

// Dynamic UI Screen Catcher (Catch-All)
// Allows URLs like /admin/dashboard to resolve to Admin\UsersManager screen class
// Now also handles the root path '/' defaulting to 'landing'
// Must be the LAST route definition to not intercept other specific routes
Route::get('/{screen?}', function (?string $screen = 'home') {
    if ($screen === 'favicon.ico') {
        return abort(404);
    }

    /** @var UsimConfig $usimConfig */
    $usimConfig = app(UsimConfig::class);

    // Check if headless mode is enabled.
    if ($usimConfig->headlessMode) {
        return response()->json([
            'error' => 'Headless mode enabled',
            'message' => 'USIM is running in headless mode. Use /api/ui endpoints directly.',
            'api_documentation' => 'Consume GET /api/ui/{screen} for initial load and POST /api/ui-event for interactions.',
        ], 406);
    }

    $screenRoute = $screen ?? 'home';
    $reset = (bool) request()->query('reset', false);

    return view('usim::app', [
        'screen' => $screenRoute,
        'reset' => $reset,
    ]);
})->where('screen', '^(?!api|docs|terms|vendor|storage|css|js|images|telescope|_debugbar).*$')->name('ui.catchall');
