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
    protected string $teamName;

    public function __construct(string $jobId, array $code, string $teamName)
    {
        $this->jobId = $jobId;
        $this->code = $code;
        $this->teamName = $teamName;
    }

    public function handle(): void
    {
        $total = count($this->code);
        $service = new JiraService();
        $teamName = $this->teamName;

        foreach ($this->code as $index => $ticketNumber) {
            $result = $service->getTicket($ticketNumber, $teamName);

            // Tembakkan progres terupdate (persentase) ke channel WebSocket
            $percent = (int) round((($index + 1) / $total) * 100);
            broadcast(new TaskProgressUpdated($this->jobId, $percent, $result));
        }
    }
}
