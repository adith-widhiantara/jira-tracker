<?php

namespace App\Jobs;

use App\Events\TaskProgressUpdated;
use App\Http\Services\JiraService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessHeavyTask implements ShouldQueue
{
    use Queueable;

    protected string $jobId;
    protected string $start;
    protected string $end;

    public function __construct(string $jobId, int $start, int $end)
    {
        $this->jobId = $jobId;
        $this->start = $start;
        $this->end = $end;
    }

    public function handle(): void
    {
        $total = $this->end - $this->start + 1;

        for ($i = $this->start; $i <= $this->end + 1; $i++) {
            $service = new JiraService();
            $result = $service->getTicket($i);

            // Tembakkan progres terupdate (persentase) ke channel WebSocket
            $percent = (int) round((($i - $this->start + 1) / $total) * 100);
            broadcast(new TaskProgressUpdated($this->jobId, $percent, $result));
        }
    }
}
