<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Start every test at midday UTC of the current date, so the server's date and a user's date agree in
     * every timezone between UTC-11 and UTC+11. Without this, tests that build dates with `now()` fail
     * between midnight UTC and the user's midnight (e.g. 00:00-05:00 UTC for America/Bogota), because the
     * app correctly judges "today" in the user's timezone. Tests that need another moment call travelTo().
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->utc()->setTime(12, 0));
    }
}
