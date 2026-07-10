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
    protected array $code;

    public function __construct(string $jobId, array $code)
    {
        $this->jobId = $jobId;
        $this->code = $code;
    }

    public function handle(): void
    {
        $total = count($this->code);
        $service = new JiraService();

        foreach ($this->code as $index => $ticketNumber) {
            $result = $service->getTicket($ticketNumber);

            // Tembakkan progres terupdate (persentase) ke channel WebSocket
            $percent = (int) round((($index + 1) / $total) * 100);
            broadcast(new TaskProgressUpdated($this->jobId, $percent, $result));
        }
    }
}
