<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests render full Inertia pages through the app layout;
        // don't require built assets (CI runs without a Vite manifest).
        $this->withoutVite();
    }
}
