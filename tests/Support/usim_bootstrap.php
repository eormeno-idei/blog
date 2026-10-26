<?php

use Tests\Support\UiScenario;
use Tests\TestCase;

// @usim: feature="core", type="test"

require_once __DIR__.'/UiScreenTestHelpers.php';
require_once __DIR__.'/UiMemoryRenderer.php';
require_once __DIR__.'/UiComponentRef.php';
require_once __DIR__.'/UiScenario.php';
require_once __DIR__.'/UiPayloadHelpers.php';

if (! function_exists('uiScenario')) {
    function uiScenario(TestCase $test, string $screenClass, array $query = []): UiScenario
    {
        return UiScenario::boot($test, $screenClass, $query);
    }
}
