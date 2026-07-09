<?php

namespace App\Jobs;

use App\Events\TaskProgressUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessHeavyTask implements ShouldQueue
{
    use Queueable;

    protected string $jobId;

    public function __construct(string $jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $totalSteps = 100;

        for ($i = 1; $i <= $totalSteps; $i++) {
            // Simulasi pengerjaan komputasi berat (0.1 detik per step)
            usleep(100000); 

            // Tembakkan progres terupdate ke channel WebSocket
            broadcast(new TaskProgressUpdated($this->jobId, $i));
        }
    }
}
