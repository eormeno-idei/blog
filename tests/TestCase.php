<?php

// @usim: feature="core", type="test"

namespace Tests;

use Idei\Usim\Support\UIIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Tests\Traits\UsimTestHelpers;

/**
 * @method TestResponse postJson(string $uri, array $data = [], array $headers = [], int $options = 0)
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    use UsimTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        // UIIdGenerator only resets automatically on Octane's RequestReceived event,
        // which never fires in tests. Without this, its static per-context caches
        // accumulate across the whole suite (single PHP process) until a context's
        // 9999-slot ID space saturates, causing an infinite loop in generateFromName().
        UIIdGenerator::reset();
    }
}
