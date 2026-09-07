<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_only_sees_their_own_courses(): void
    {
        $me    = $this->student('20230099');
        $other = $this->student('20230150', 'Maya Karim');

        $this->courseFor($me, 'CSC400');
        $this->courseFor($other, 'BIO101');

        $this->actingAs($me, 'sanctum')->getJson('/api/courses')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', 'CSC400');
    }

    public function test_a_student_can_create_a_course(): void
    {
        $me = $this->student('20230099');

        $this->actingAs($me, 'sanctum')->postJson('/api/courses', [
            'name'       => 'Web Programming',
            'code'       => 'CSC400',
            'instructor' => 'Dr. Ali Hadi',
            'semester'   => 'Spring 2026',
            'grade'      => 'A-',
            'credits'    => 3,
        ])->assertCreated()->assertJsonPath('code', 'CSC400');

        $this->assertDatabaseHas('courses', ['code' => 'CSC400', 'user_id' => $me->id]);
    }

    public function test_an_invalid_grade_is_rejected(): void
    {
        $me = $this->student('20230099');

        $this->actingAs($me, 'sanctum')->postJson('/api/courses', [
            'name'       => 'Web Programming',
            'code'       => 'CSC400',
            'instructor' => 'Dr. Ali Hadi',
            'semester'   => 'Spring 2026',
            'grade'      => 'Z+',
        ])->assertStatus(422)->assertJsonValidationErrors('grade');
    }

    public function test_a_student_cannot_change_or_delete_someone_elses_course(): void
    {
        $me     = $this->student('20230099');
        $other  = $this->student('20230150', 'Maya Karim');
        $theirs = $this->courseFor($other, 'BIO101');

        $this->actingAs($me, 'sanctum')->putJson('/api/courses/'.$theirs->id, [
            'name'       => 'Hijacked',
            'code'       => 'BIO101',
            'instructor' => 'Dr. Test',
            'semester'   => 'Spring 2026',
        ])->assertForbidden();

        $this->actingAs($me, 'sanctum')->deleteJson('/api/courses/'.$theirs->id)->assertForbidden();

        $this->assertDatabaseHas('courses', ['id' => $theirs->id, 'name' => 'BIO101 Course']);
    }

    public function test_deleting_a_course_removes_its_tasks(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC400');
        $task   = $this->taskFor($course);

        $this->actingAs($me, 'sanctum')->deleteJson('/api/courses/'.$course->id)->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_a_task_cannot_be_attached_to_someone_elses_course(): void
    {
        $me          = $this->student('20230099');
        $other       = $this->student('20230150', 'Maya Karim');
        $theirCourse = $this->courseFor($other, 'BIO101');

        $this->actingAs($me, 'sanctum')->postJson('/api/tasks', [
            'course_id' => $theirCourse->id,
            'title'     => 'Sneaky task',
            'type'      => 'Assignment',
            'priority'  => 'High',
            'due_date'  => '2026-09-10',
        ])->assertForbidden();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_an_invalid_task_type_is_rejected(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC400');

        $this->actingAs($me, 'sanctum')->postJson('/api/tasks', [
            'course_id' => $course->id,
            'title'     => 'Bad type',
            'type'      => 'Homework',
            'priority'  => 'High',
            'due_date'  => '2026-09-10',
        ])->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_toggling_a_task_flips_its_completion(): void
    {
        $me   = $this->student('20230099');
        $task = $this->taskFor($this->courseFor($me, 'CSC400'));

        $this->actingAs($me, 'sanctum')->patchJson('/api/tasks/'.$task->id.'/toggle')
            ->assertOk()->assertJsonPath('is_completed', true);

        $this->actingAs($me, 'sanctum')->patchJson('/api/tasks/'.$task->id.'/toggle')
            ->assertOk()->assertJsonPath('is_completed', false);
    }

    public function test_a_student_cannot_toggle_or_delete_someone_elses_task(): void
    {
        $me        = $this->student('20230099');
        $other     = $this->student('20230150', 'Maya Karim');
        $theirTask = $this->taskFor($this->courseFor($other, 'BIO101'));

        $this->actingAs($me, 'sanctum')->patchJson('/api/tasks/'.$theirTask->id.'/toggle')->assertForbidden();
        $this->actingAs($me, 'sanctum')->deleteJson('/api/tasks/'.$theirTask->id)->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $theirTask->id, 'is_completed' => false]);
    }
}
