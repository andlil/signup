<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Render views without built frontend assets, so tests also run in CI.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
