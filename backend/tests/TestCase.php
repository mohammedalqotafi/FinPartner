<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable rate limiting during tests so property-based tests
        // with many iterations are not throttled (HTTP 429).
        RateLimiter::clear('api');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }
}
