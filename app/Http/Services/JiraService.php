<?php

namespace App\Http\Services;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraService extends Service
{
    public function getTicket(int $ticket): void
    {
        $url = config('jira.url') . '/issue/ECHO-' . $ticket . '?expand=changelog';

        $response = Http::withBasicAuth(config('jira.username'), config('jira.token'))
            ->get($url);

        $result = $response->json();

        if (!isset($result['key']) || !isset($result['fields'])) {
            Log::debug([
                'error' => 'not found key',
                'url' => $url,
                'data' => $result
            ]);

            return;
        }

        $statusHistories = collect(data_get($result, 'changelog.histories', []))
            ->flatMap(function ($history) {
                return collect(data_get($history, 'items', []))->map(function ($item) use ($history) {
                    $item['author'] = data_get($history, 'author.displayName');
                    $item['created_at'] = data_get($history, 'created');
                    return $item;
                });
            })
            ->where('field', 'status')
            ->map(function ($item) {
                return [
                    'fromString' => data_get($item, 'fromString'),
                    'toString'   => data_get($item, 'toString'),
                    'author'     => data_get($item, 'author'),
                    'created_at' => data_get($item, 'created_at'),
                ];
            })->all();

        $ticketModel = Ticket::updateOrCreate(
            ['link_ticket' => 'https://sevima.atlassian.net/browse/ECHO-' . $ticket],
            [
                'request_key' => 'ECHO-' . $ticket,
                'response_key' => $result['key'],
                'task_created' => Carbon::parse($result['fields']['created']),
                'summary' => $result['fields']['summary'],
                'assignee' => $result['fields']['assignee']['displayName'] ?? null,
                'estimate_timetracking' => $result['fields']['timetracking']['originalEstimateSeconds'] ?? null,
                'remaining_timetracking' => $result['fields']['timetracking']['remainingEstimateSeconds'] ?? null,
            ]
        );

        $ticketModel->histories()->delete();

        $previousCreatedAt = null;
        foreach ($statusHistories as $history) {
            $createdAt = Carbon::parse($history['created_at']);

            $ticketModel->histories()->create([
                'from' => $history['fromString'],
                'to' => $history['toString'],
                'author' => $history['author'],
                'created_at' => $createdAt,
                'interval_previous_history' => $previousCreatedAt ? (int) $createdAt->diffInSeconds($previousCreatedAt) : 0,
            ]);

            $previousCreatedAt = $createdAt;
        }
    }
}
