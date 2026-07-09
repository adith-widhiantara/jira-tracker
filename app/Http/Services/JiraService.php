<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraService extends Service
{
    public function getTicket(int $ticket): void
    {
        $response = Http::withBasicAuth(config('jira.username'), config('jira.token'))
            ->get(config('jira.url') . '/issue/ECHO-' . $ticket . '?expand=changelog');

        $result = $response->json();

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

        Log::debug([
            'ticket' => $ticket,
            'summary' => $result['fields']['summary'],
            'history' => $statusHistories
        ]);
    }
}
