<?php

namespace Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Sammyjo20\LaravelHaystack\Concerns\Stackable;
use Sammyjo20\LaravelHaystack\Contracts\StackableJob;

class LongReleaseJob implements ShouldQueue, StackableJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Stackable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public int|CarbonInterface $releaseUntil)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Sammyjo20\LaravelHaystack\Tests\Exceptions\StackableException
     */
    public function handle()
    {
        $shouldRelease = cache()->get('release') === true;

        if ($shouldRelease === true) {
            cache()->set('release', false);
            $this->longRelease($this->releaseUntil);
        } else {
            cache()->set('longReleaseFinished', true);
        }

        $this->nextJob();
    }
}
