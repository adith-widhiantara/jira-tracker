<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW jira_bounce_duration_report AS
            SELECT
                t.request_key,
                t.summary,
                t.assignee,
                th."from"           AS status_asal,
                th."to"             AS status_tujuan,
                th.author           AS dipindahkan_oleh,
                th.created_at       AS waktu_bounce,
                th.interval_previous_history AS durasi_detik_raw,
                CONCAT(
                    (th.interval_previous_history / 86400)::int, ' hari ',
                    ((th.interval_previous_history % 86400) / 3600)::int, ' jam ',
                    ((th.interval_previous_history % 3600) / 60)::int, ' menit ',
                    (th.interval_previous_history % 60)::int, ' detik'
                ) AS durasi_bounce
            FROM ticket_histories th
            JOIN tickets t ON t.id = th.ticket_id
            ORDER BY t.request_key, th.created_at;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS jira_bounce_duration_report;");
    }
};
