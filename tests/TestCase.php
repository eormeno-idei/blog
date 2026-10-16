<?php

namespace Tests;

use Tests\Traits\UsimTestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
abstract class TestCase extends BaseTestCase
{
    use UsimTestHelpers;
    use RefreshDatabase;
    //
}