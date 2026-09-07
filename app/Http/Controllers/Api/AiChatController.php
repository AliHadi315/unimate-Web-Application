<?php

namespace App\Http\Controllers\Api;

use Anthropic\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    /*  GET /api/ai/status — tells the frontend whether real AI is available  */

    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->configured(),
        ]);
    }

    // Both the API key and a model ID must be set in .env
    private function configured(): bool
    {
        return (bool) (config('services.anthropic.key') && config('services.anthropic.model'));
    }

    /*  POST /api/ai/chat  */

    public function chat(Request $request): JsonResponse
    {
        abort_unless($this->configured(), 503, 'AI is not configured.');

        $validated = $request->validate([
            'messages'                => ['required', 'array', 'max:40'],
            'messages.*.role'         => ['required', 'in:user,ai'],
            'messages.*.content'      => ['required', 'string', 'max:12000'], // roomy enough for attached file text
        ]);

        $user    = $request->user();
        $courses = $user->courses()->get(['id', 'name', 'code', 'semester']);
        $tasks   = $user->tasks()->orderBy('due_date')->get(['course_id', 'title', 'type', 'priority', 'due_date', 'is_completed']);

        $system = $this->buildSystemPrompt($user->full_name, $courses, $tasks);

        // The API expects role "assistant"; our frontend stores "ai"
        $messages = array_map(fn ($m) => [
            'role'    => $m['role'] === 'ai' ? 'assistant' : 'user',
            'content' => $m['content'],
        ], $validated['messages']);

        try {
            $client = new Client(apiKey: config('services.anthropic.key'));

            $response = $client->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 2048,
                system: $system,
                messages: $messages,
            );

            $reply = '';
            foreach ($response->content as $block) {
                if ($block->type === 'text') {
                    $reply .= $block->text;
                }
            }

            return response()->json(['reply' => $reply !== '' ? $reply : 'Sorry, I could not come up with a response.']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'The AI service is unavailable right now.'], 502);
        }
    }

    /*  POST /api/ai/syllabus — propose tasks from a syllabus, saving nothing  */

    public function syllabus(Request $request): JsonResponse
    {
        abort_unless(
            $this->configured(),
            503,
            "The AI assistant isn't set up on this server, so syllabus import is unavailable."
        );

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            // ponytail: plain text only — paste it or upload a text file.
            // Add a PDF parser here if reading the PDF directly ever matters.
            'text'      => ['required', 'string', 'min:40', 'max:40000'],
        ]);

        $course = $request->user()->courses()->findOrFail($validated['course_id']);

        try {
            $client = new Client(apiKey: config('services.anthropic.key'));

            $response = $client->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 4096,
                system: $this->syllabusPrompt($course->code, $course->name),
                messages: [['role' => 'user', 'content' => $validated['text']]],
            );

            $raw = '';
            foreach ($response->content as $block) {
                if ($block->type === 'text') {
                    $raw .= $block->text;
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'The AI service is unavailable right now.'], 502);
        }

        return response()->json(['tasks' => $this->parseSyllabusTasks($raw)]);
    }

    private function syllabusPrompt(string $code, string $name): string
    {
        $today = now()->toDateString();
        $year  = now()->year;

        return <<<PROMPT
        You read university syllabi and pull out the graded work a student has to hand in or sit.

        The syllabus is for {$code} — {$name}. Today is {$today}.

        Return ONLY a JSON array, no prose and no code fences. Each element must be:
        {"title": string, "type": "Assignment"|"Exam"|"Project", "priority": "Low"|"Medium"|"High", "due_date": "YYYY-MM-DD"}

        Rules:
        - One entry per graded item: assignments, quizzes, midterms, finals, projects, presentations.
        - Quizzes, midterms and finals are "Exam". Multi-week deliverables are "Project". Everything else is "Assignment".
        - Weight the priority by how much the item counts for: finals and big projects are "High".
        - Only include an item if you can resolve a real calendar date. If the syllabus says a weekday or
          "Week 5" with no date anywhere, leave that item out rather than guessing.
        - If a date has no year, assume the academic year around {$year}.
        - Keep titles short and specific, like "Midterm Exam" or "Assignment 2 — Normalization".
        - Return [] if the text is not a syllabus or lists no dated work.
        PROMPT;
    }

    /*  Model output is untrusted: keep only well-formed, in-enum, really-dated rows  */

    public function parseSyllabusTasks(string $raw): array
    {
        $raw = trim($raw);

        // Tolerate ```json fences and any stray prose around the array
        $start = strpos($raw, '[');
        $end   = strrpos($raw, ']');
        if ($start === false || $end === false || $end < $start) {
            return [];
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            return [];
        }

        $types      = ['Assignment', 'Exam', 'Project'];
        $priorities = ['Low', 'Medium', 'High'];
        $tasks      = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $date  = trim((string) ($item['due_date'] ?? ''));

            if ($title === '' || ! $this->isRealDate($date)) {
                continue;
            }

            $type     = $item['type'] ?? '';
            $priority = $item['priority'] ?? '';

            $tasks[] = [
                'title'    => mb_substr($title, 0, 255),
                'type'     => in_array($type, $types, true) ? $type : 'Assignment',
                'priority' => in_array($priority, $priorities, true) ? $priority : 'Medium',
                'due_date' => $date,
            ];

            if (count($tasks) === 40) {
                break;
            }
        }

        return $tasks;
    }

    private function isRealDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$y, $m, $d] = array_map('intval', explode('-', $date));

        return checkdate($m, $d, $y);
    }

    /*  Give the model the student's real data so answers are grounded  */

    private function buildSystemPrompt(string $name, $courses, $tasks): string
    {
        $courseLines = $courses->map(
            fn ($c) => "- [{$c->id}] {$c->code} {$c->name} ({$c->semester})"
        )->implode("\n") ?: '(none)';

        $taskLines = $tasks->map(function ($t) {
            $status = $t->is_completed ? 'completed' : 'pending';

            return "- {$t->title} | {$t->type} | priority {$t->priority} | due {$t->due_date} | {$status} | course [{$t->course_id}]";
        })->implode("\n") ?: '(none)';

        $today = now()->toDateString();

        return <<<PROMPT
You are the study assistant inside UniMate, a university productivity app. You are talking to {$name}, a university student. Today's date is {$today}.

Their courses:
{$courseLines}

Their tasks:
{$taskLines}

Help with study planning, prioritization, explanations of academic concepts, and questions about their courses and deadlines. Keep answers short and practical — a few sentences or a short list. Use plain text, no markdown headers. When referring to tasks, use their titles and course codes, never the internal ids in brackets.
PROMPT;
    }
}
