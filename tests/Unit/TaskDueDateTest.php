<?php

namespace Tests\Unit;

use App\Models\Task;
use PHPUnit\Framework\TestCase;

class TaskDueDateTest extends TestCase
{
    public function test_due_date_is_trimmed_to_a_plain_date(): void
    {
        $task = new Task();
        $task->due_date = '2026-09-10 00:00:00';

        // The frontend compares raw YYYY-MM-DD strings, so the time must not leak through
        $this->assertSame('2026-09-10', $task->due_date);
    }

    public function test_missing_due_date_becomes_an_empty_string(): void
    {
        $this->assertSame('', (new Task())->due_date);
    }
}
