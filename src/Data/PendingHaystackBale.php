<?php

namespace Sammyjo20\LaravelHaystack\Data;

use Sammyjo20\LaravelHaystack\Contracts\StackableJob;

class PendingHaystackBale
{
    /**
     * Constructor
     *
     * @param  StackableJob  $job
     * @param  int  $delayInSeconds
     * @param  string|null  $queue
     * @param  string|null  $connection
     */
    public function __construct(
        public StackableJob $job,
        public int $delayInSeconds = 0,
        public ?string $queue = null,
        public ?string $connection = null,
    ) {
        $nativeDelay = $this->job->delay;
        $nativeQueue = $this->job->queue;
        $nativeConnection = $this->job->connection;

        if (isset($nativeDelay) && $this->delayInSeconds <= 0) {
            $this->delayInSeconds = $nativeDelay;
        }

        if (isset($nativeQueue) && ! isset($this->queue)) {
            $this->queue = $nativeQueue;
        }

        if (isset($nativeConnection) && ! isset($this->connection)) {
            $this->connection = $nativeConnection;
        }
    }
}
