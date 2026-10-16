<?php

use Illuminate\Support\Facades\Route;

// Disabled by usim:install to allow USIM catch-all route.
// Route::get('/', function () {
//     return view('welcome');
// });



// Dynamic UI Screen Catcher (Catch-All)
// Allows URLs like /admin/dashboard to resolve to Admin\UsersManager screen class
// Now also handles the root path '/' defaulting to 'landing'
// Must be the LAST route definition to not intercept other specific routes
Route::get('/{screen?}', function (?string $screen = 'home') {
    if ($screen === 'favicon.ico') {
        return abort(404);
    }

    /** @var \Idei\Usim\Support\UsimConfig $usimConfig */
    $usimConfig = app(\Idei\Usim\Support\UsimConfig::class);

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
