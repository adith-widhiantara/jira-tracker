<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Services\Service;
use App\Http\Services\TaskService;
use App\Jobs\ProcessHeavyTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function service(): Service
    {
        return new TaskService();
    }

    public function trigger(Request $request): JsonResponse
    {
        /** @var TaskService $service */
        $service = $this->service();

        return $service->runTracker($request);
    }
}
