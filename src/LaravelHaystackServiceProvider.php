<?php

namespace Sammyjo20\LaravelHaystack;

use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Queue\Events\JobProcessed;
use Sammyjo20\LaravelHaystack\Contracts\StackableJob;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sammyjo20\LaravelHaystack\Actions\ProcessCompletedJob;
use Sammyjo20\LaravelHaystack\Console\Commands\ResumeHaystacks;

class LaravelHaystackServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-haystack')
            ->hasConfigFile()
            ->hasMigrations([
                'create_haystacks_table',
                'create_haystack_bales_table',
                'create_haystack_data_table',
            ])
            ->hasCommand(ResumeHaystacks::class);
    }

    /**
     * @return void
     */
    public function bootingPackage()
    {
        if (config('haystack.process_automatically', false) === true) {
            $this->listenToJobs();
        }
    }

    /**
     * Listen to jobs.
     *
     * @return void
     */
    public function listenToJobs(): void
    {
        // We'll firstly append the haystack_id onto the queued job's
        // payload. This will be resolved in our process completed
        // job logic.

        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            $jobData = $payload['data'];
            $command = $payload['data']['command'] ?? null;

            if ($command instanceof StackableJob) {
                $jobData = array_merge($payload['data'], [
                    'haystack_id' => $command->getHaystack()->getKey(),
                ]);
            }

            return ['data' => $jobData];
        });

        // After every processed job, we will execute this, which will determine if it should
        // run the next job in the chain.

        Queue::after(fn (JobProcessed $event) => (new ProcessCompletedJob($event))->execute());
    }
}
