<?php

use Sammyjo20\LaravelHaystack\LaravelHaystackServiceProvider;
use Sammyjo20\LaravelHaystack\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in(__DIR__);

function automaticProcessing(): void
{
    config()->set('haystack.process_automatically', true);

    // It's a bit hacky, but we'll run the "bootingPackage" method
    // on the provider to start recording events.

    (new LaravelHaystackServiceProvider(app()))->bootingPackage();
}
