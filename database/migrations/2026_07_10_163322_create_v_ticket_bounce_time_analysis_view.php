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
            CREATE OR REPLACE VIEW v_ticket_bounce_time_analysis AS
            WITH bounce_flags AS (
                SELECT
                    th.ticket_id,
                    th."from",
                    th."to",
                    CASE WHEN th."from" = 'In Progress'
                            AND th."to" IN ('Testing', 'Feedback QA')
                        THEN 1 ELSE 0 END AS ke_qa,
                    CASE WHEN th."from" IN ('Testing', 'Feedback QA')
                            AND th."to" = 'In Progress'
                        THEN 1 ELSE 0 END AS balik_ke_progress
                FROM ticket_histories th
            ),
            ticket_summary AS (
                SELECT
                    ticket_id,
                    SUM(ke_qa)              AS jumlah_masuk_qa,
                    SUM(balik_ke_progress)  AS jumlah_bounce,
                    SUM(ke_qa) + SUM(balik_ke_progress) AS total_perpindahan
                FROM bounce_flags
                GROUP BY ticket_id
            ),
            author_dari_testing AS (
                SELECT
                    th.ticket_id,
                    STRING_AGG(DISTINCT th.author, ', ') AS authors
                FROM ticket_histories th
                WHERE th."from" = 'Testing'
                GROUP BY th.ticket_id
            ),
            real_time AS (
                SELECT
                    th.ticket_id,
                    SUM(th.interval_previous_history) AS real_timetracking_detik
                FROM ticket_histories th
                WHERE th."from" IN ('In Progress', 'Review Tech Lead/EM')
                GROUP BY th.ticket_id
            ),
            final_calc AS (
                SELECT
                    t.id,
                    t.request_key,
                    t.summary,
                    t.assignee,
                    t.estimate_timetracking              AS estimate_timetracking_detik,
                    rt.real_timetracking_detik           AS real_timetracking_detik_raw,
                    COALESCE(rt.real_timetracking_detik, 0) AS real_timetracking_detik,
                    COALESCE(rt.real_timetracking_detik, 0) - t.estimate_timetracking AS selisih_detik
                FROM tickets t
                LEFT JOIN ticket_summary ts ON ts.ticket_id = t.id
                LEFT JOIN author_dari_testing adt ON adt.ticket_id = t.id
                LEFT JOIN real_time rt ON rt.ticket_id = t.id
            )
            SELECT
                fc.request_key AS jira_code,
                SPLIT_PART(fc.request_key, '-', 1)    AS tim,
                fc.summary,
                fc.assignee,
                fc.estimate_timetracking_detik,
                fc.real_timetracking_detik,
                fc.selisih_detik,
                CASE
                    WHEN fc.real_timetracking_detik_raw IS NULL THEN 'Belum pernah masuk status In Progress'
                    WHEN fc.estimate_timetracking_detik IS NULL THEN 'Estimasi waktu belum diisi'
                    WHEN fc.selisih_detik < 0 THEN 'Lebih cepat'
                    WHEN fc.selisih_detik > 0 THEN 'Lebih lambat'
                    ELSE 'Tepat sesuai estimasi'
                END AS status_estimasi,
                CASE
                    WHEN fc.real_timetracking_detik_raw IS NULL THEN NULL
                    WHEN fc.estimate_timetracking_detik IS NULL THEN NULL
                    WHEN fc.selisih_detik = 0 THEN NULL
                    ELSE CONCAT(
                        (ABS(fc.selisih_detik) / 86400)::int, ' hari ',
                        ((ABS(fc.selisih_detik) % 86400) / 3600)::int, ' jam ',
                        ((ABS(fc.selisih_detik) % 3600) / 60)::int, ' menit ',
                        (ABS(fc.selisih_detik) % 60)::int, ' detik'
                    )
                END AS durasi_selisih
            FROM final_calc fc;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_ticket_bounce_time_analysis;");
    }
};
