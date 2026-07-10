<?php

namespace App\Http\Services;

use App\Jobs\ProcessHeavyTask;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskService extends Service
{
    public function runTracker(Request $request)
    {
        // Buat ID unik untuk melacak progress transaksi ini
        $jobId = (string) Str::uuid();

        $codepattern = (string) $request->input('codepattern');

        $code = (array) $this->updateToCode($codepattern);

        // Lempar ke Queue Worker (Redis)
        ProcessHeavyTask::dispatch($jobId, $code);

        // Langsung kembalikan respons ke klien tanpa menunggu proses di atas selesai
        return response()->json([
            'status' => 'queued',
            'jobId' => $jobId
        ]);
    }

    private function updateToCode(string $codepattern): array
    {
        $codes = [];

        foreach (explode(',', $codepattern) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (str_contains($part, '-')) {
                [$from, $to] = explode('-', $part, 2);
                $codes = array_merge($codes, range((int) trim($from), (int) trim($to)));
            } else {
                $codes[] = (int) $part;
            }
        }

        return array_values(array_unique($codes));
    }
}
