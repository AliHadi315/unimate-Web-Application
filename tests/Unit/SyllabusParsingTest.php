<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\AiChatController;
use PHPUnit\Framework\TestCase;

/**
 * The model's reply is untrusted text. These cover the shapes it really
 * produces — fenced JSON, chatty preambles, half-filled rows — and prove
 * nothing invalid reaches the task API.
 */
class SyllabusParsingTest extends TestCase
{
    private AiChatController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AiChatController();
    }

    private function parse(string $raw): array
    {
        return $this->controller->parseSyllabusTasks($raw);
    }

    public function test_it_reads_a_plain_json_array(): void
    {
        $tasks = $this->parse('[{"title":"Midterm Exam","type":"Exam","priority":"High","due_date":"2026-10-14"}]');

        $this->assertSame([[
            'title'    => 'Midterm Exam',
            'type'     => 'Exam',
            'priority' => 'High',
            'due_date' => '2026-10-14',
        ]], $tasks);
    }

    public function test_it_survives_code_fences_and_chatter(): void
    {
        $raw = "Sure! Here are the tasks I found:\n```json\n"
            .'[{"title":"Lab 1","type":"Assignment","priority":"Low","due_date":"2026-09-20"}]'
            ."\n```\nLet me know if you need more.";

        $this->assertCount(1, $this->parse($raw));
        $this->assertSame('Lab 1', $this->parse($raw)[0]['title']);
    }

    public function test_rows_without_a_usable_date_are_dropped(): void
    {
        $raw = '[
            {"title":"No date","type":"Exam","priority":"High"},
            {"title":"Week 5 quiz","type":"Exam","priority":"High","due_date":"Week 5"},
            {"title":"Impossible","type":"Exam","priority":"High","due_date":"2026-02-30"},
            {"title":"Keeper","type":"Exam","priority":"High","due_date":"2026-11-03"}
        ]';

        $tasks = $this->parse($raw);

        $this->assertCount(1, $tasks);
        $this->assertSame('Keeper', $tasks[0]['title']);
    }

    public function test_unknown_types_and_priorities_fall_back_to_safe_values(): void
    {
        $raw = '[{"title":"Reading","type":"Homework","priority":"Urgent","due_date":"2026-09-30"}]';
        $task = $this->parse($raw)[0];

        // The tasks table only accepts these enums
        $this->assertSame('Assignment', $task['type']);
        $this->assertSame('Medium', $task['priority']);
    }

    public function test_untitled_rows_and_junk_entries_are_skipped(): void
    {
        $raw = '[{"title":"   ","due_date":"2026-09-30"}, "not an object", 42, {"due_date":"2026-09-30"}]';

        $this->assertSame([], $this->parse($raw));
    }

    public function test_it_returns_nothing_for_non_json_replies(): void
    {
        $this->assertSame([], $this->parse('I could not find any assignments in that text.'));
        $this->assertSame([], $this->parse(''));
        $this->assertSame([], $this->parse('[not valid json at all'));
    }

    public function test_long_titles_are_trimmed_to_the_column_size(): void
    {
        $raw = '[{"title":"'.str_repeat('a', 400).'","type":"Exam","priority":"High","due_date":"2026-09-30"}]';

        $this->assertSame(255, mb_strlen($this->parse($raw)[0]['title']));
    }

    public function test_a_runaway_reply_cannot_flood_the_task_list(): void
    {
        $rows = [];
        for ($i = 0; $i < 200; $i++) {
            $rows[] = '{"title":"Task '.$i.'","type":"Exam","priority":"High","due_date":"2026-09-30"}';
        }

        $this->assertCount(40, $this->parse('['.implode(',', $rows).']'));
    }
}
