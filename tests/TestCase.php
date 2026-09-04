<?php

namespace Tests;

use App\Services\FacilitySettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * The facility profile every feature test starts with, so that the
     * first-run wizard only intercepts the tests that opt out of it.
     *
     * @var array{name: string, state: string, lga: string, code: string}
     */
    public const TEST_FACILITY = [
        'name' => 'Test General Hospital',
        'state' => 'Lagos',
        'lga' => 'Ikeja',
        'code' => 'TEST/001',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('global_properties')) {
            app(FacilitySettings::class)->complete(self::TEST_FACILITY);
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
