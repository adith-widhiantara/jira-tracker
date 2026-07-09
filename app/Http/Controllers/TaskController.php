<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessHeavyTask;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function trigger(Request $request)
    {
        // Buat ID unik untuk melacak progress transaksi ini
        $jobId = (string) Str::uuid();

        $start = (int) $request->input('start');
        $end = (int) $request->input('end');

        // Lempar ke Queue Worker (Redis)
        ProcessHeavyTask::dispatch($jobId, $start, $end);

        // Langsung kembalikan respons ke klien tanpa menunggu proses di atas selesai
        return response()->json([
            'status' => 'queued',
            'jobId' => $jobId
        ]);
    }
}
