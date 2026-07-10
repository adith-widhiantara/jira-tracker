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
            CREATE OR REPLACE VIEW jira_bounce_report AS
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
            )
            SELECT
                t.request_key as jira_code,
                SPLIT_PART(t.request_key, '-', 1)    AS tim,
                t.summary,
                t.assignee,
                COALESCE(ts.jumlah_masuk_qa, 0)      AS jumlah_masuk_qa,
                COALESCE(ts.jumlah_bounce, 0)        AS jumlah_bounce,
                COALESCE(ts.total_perpindahan, 0)    AS total_perpindahan,
                adt.authors                          AS author_dari_testing
            FROM tickets t
            LEFT JOIN ticket_summary ts ON ts.ticket_id = t.id
            LEFT JOIN author_dari_testing adt ON adt.ticket_id = t.id
            ORDER BY ts.jumlah_bounce DESC NULLS LAST, ts.total_perpindahan DESC NULLS LAST;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS jira_bounce_report;");
    }
};
