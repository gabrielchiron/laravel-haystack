<?php

use function Pest\Laravel\travel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Sammyjo20\LaravelHaystack\Models\Haystack;
use Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs\FailJob;
use Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs\NameJob;
use Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs\SetDataJob;
use Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs\PauseNextJob;
use Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs\LongReleaseJob;
use Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs\OrderCheckCacheJob;
use Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs\AppendingOrderCheckCacheJob;

test('you can start a haystack', function () {
    Queue::fake();

    $haystack = Haystack::build()
        ->addJob(new NameJob('Sam'))
        ->addJob(new NameJob('Steve'))
        ->create();

    Queue::assertNothingPushed();

    $haystack->start();

    expect($haystack->started)->toBeTrue();

    Queue::assertPushed(NameJob::class, function ($job) {
        return $job->name === 'Sam';
    });
});

test('you can finish a haystack early', function () {
    Queue::fake();

    $variable = 0;

    $haystack = Haystack::build()
        ->addJob(new NameJob('Sam'))
        ->then(function () use (&$variable) {
            $variable++;
        })
        ->create();

    expect($variable)->toEqual(0);

    $haystack->finish();

    expect($variable)->toEqual(1);

    Queue::assertNothingPushed();
});

test('you can fail a haystack', function () {
    Queue::fake();

    $variable = 0;

    $haystack = Haystack::build()
        ->addJob(new NameJob('Sam'))
        ->catch(function () use (&$variable) {
            $variable++;
        })
        ->create();

    expect($variable)->toEqual(0);

    $haystack->fail();

    expect($variable)->toEqual(1);

    Queue::assertNothingPushed();
});

test('jobs are processed in the right order', function () {
    Haystack::build()
        ->addJob(new OrderCheckCacheJob('Sam'))
        ->addJob(new OrderCheckCacheJob('Steve'))
        ->addJob(new OrderCheckCacheJob('Taylor'))
        ->dispatch();

    expect(cache()->get('order'))->toEqual(['Sam', 'Steve', 'Taylor']);
});

test('you can append a job onto the haystack in a job and it is run at the end', function () {
    Haystack::build()
        ->addJob(new AppendingOrderCheckCacheJob('Sam'))
        ->addJob(new OrderCheckCacheJob('Taylor'))
        ->dispatch();

    expect(cache()->get('order'))->toEqual(['Sam', 'Taylor', 'Sam']);
});

test('when a haystack is finished the then and finally methods are executed', function () {
    Haystack::build()
        ->addJob(new NameJob('Sam'))
        ->then(function () {
            cache()->put('then', true);
        })
        ->catch(function () {
            cache()->put('catch', true);
        })
        ->finally(function () {
            cache()->put('finally', true);
        })
        ->dispatch();

    expect(cache()->get('then'))->toBeTrue();
    expect(cache()->get('catch'))->toBeNull();
    expect(cache()->get('finally'))->toBeTrue();
});

test('when a haystack is failed the then and finally methods are executed', function () {
    Haystack::build()
        ->addJob(new FailJob())
        ->then(function () {
            cache()->put('then', true);
        })
        ->catch(function () {
            cache()->put('catch', true);
        })
        ->finally(function () {
            cache()->put('finally', true);
        })
        ->dispatch();

    expect(cache()->get('then'))->toBeNull();
    expect(cache()->get('catch'))->toBeTrue();
    expect(cache()->get('finally'))->toBeTrue();
});

test('when a haystack is paused the paused method is executed', function () {
    Haystack::build()
        ->addJob(new PauseNextJob('name', 'Sam', 300))
        ->paused(function () {
            cache()->put('paused', true);
        })
        ->dispatch();

    expect(cache()->get('paused'))->toBeTrue();
});

test('when a haystack job is long released the paused method is executed', function () {
    cache()->set('release', true);

    Haystack::build()
        ->addJob(new LongReleaseJob(300))
        ->paused(function () {
            cache()->put('paused', true);
        })
        ->dispatch();

    expect(cache()->get('paused'))->toBeTrue();
});

test('the closures can receive the data if the option is enabled', function () {
    Haystack::build()
        ->addJob(new SetDataJob('name', 'Sam'))
        ->addJob(new SetDataJob('friend', 'Steve'))
        ->addJob(new PauseNextJob('pause', true, 300))
        ->then(function ($data) {
            cache()->set('then', $data);
        })
        ->finally(function ($data) {
            cache()->set('finally', $data);
        })
        ->paused(function ($data) {
            cache()->set('paused', $data);
        })
        ->dispatch();

    travel(6)->minutes();

    $this->artisan('haystacks:resume');

    $data = new Collection([
        'name' => 'Sam',
        'friend' => 'Steve',
    ]);

    expect(cache()->get('then'))->toEqual($data);
    expect(cache()->get('finally'))->toEqual($data);
    expect(cache()->get('paused'))->toEqual($data);
});

test('the closures cannot receive the data if the option is disabled on a per builder instance', function () {
    Haystack::build()
        ->addJob(new SetDataJob('name', 'Sam'))
        ->addJob(new SetDataJob('friend', 'Steve'))
        ->addJob(new PauseNextJob('pause', true, 300))
        ->then(function ($data) {
            cache()->set('then', $data ?? 'empty');
        })
        ->finally(function ($data) {
            cache()->set('finally', $data ?? 'empty');
        })
        ->paused(function ($data) {
            cache()->set('paused', $data ?? 'empty');
        })
        ->dontReturnData()
        ->dispatch();

    travel(6)->minutes();

    $this->artisan('haystacks:resume');

    expect(cache()->get('then'))->toEqual('empty');
    expect(cache()->get('finally'))->toEqual('empty');
    expect(cache()->get('paused'))->toEqual('empty');
});

test('the closures will not receive the data if the option is enabled', function () {
    config()->set('haystack.return_all_haystack_data_when_finished', false);

    Haystack::build()
        ->addJob(new SetDataJob('name', 'Sam'))
        ->addJob(new SetDataJob('friend', 'Steve'))
        ->addJob(new PauseNextJob('pause', true, 300))
        ->then(function ($data) {
            cache()->set('then', $data ?? 'empty');
        })
        ->finally(function ($data) {
            cache()->set('finally', $data ?? 'empty');
        })
        ->paused(function ($data) {
            cache()->set('paused', $data ?? 'empty');
        })
        ->dispatch();

    travel(6)->minutes();

    $this->artisan('haystacks:resume');

    expect(cache()->get('then'))->toEqual('empty');
    expect(cache()->get('finally'))->toEqual('empty');
    expect(cache()->get('paused'))->toEqual('empty');
});
