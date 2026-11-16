<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature requests focus on application behavior; browser CSRF behavior
        // remains covered by the application's middleware stack.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
