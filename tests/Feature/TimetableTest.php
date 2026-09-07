<?php

namespace Tests\Feature;

use App\Models\Lecture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    private function lectureFor($course, array $extra = []): Lecture
    {
        return Lecture::create($extra + [
            'user_id'     => $course->user_id,
            'course_id'   => $course->id,
            'day_of_week' => 0,
            'start_time'  => '09:00',
            'end_time'    => '10:30',
            'room'        => 'B204',
        ]);
    }

    public function test_a_student_can_schedule_a_class(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC400');

        $this->actingAs($me, 'sanctum')->postJson('/api/lectures', [
            'course_id'   => $course->id,
            'day_of_week' => 2,
            'start_time'  => '14:00',
            'end_time'    => '16:00',
            'room'        => 'Lab 3',
        ])->assertCreated()
          ->assertJsonPath('room', 'Lab 3')
          ->assertJsonPath('course.code', 'CSC400');

        $this->assertDatabaseHas('lectures', ['user_id' => $me->id, 'room' => 'Lab 3']);
    }

    public function test_times_come_back_as_plain_hours_and_minutes(): void
    {
        $me = $this->student('20230099');
        $this->lectureFor($this->courseFor($me, 'CSC400'));

        // The grid maths parses HH:MM, so seconds must not leak through
        $this->actingAs($me, 'sanctum')->getJson('/api/lectures')
            ->assertOk()
            ->assertJsonPath('0.start_time', '09:00')
            ->assertJsonPath('0.end_time', '10:30');
    }

    public function test_a_class_must_end_after_it_starts(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC400');

        $this->actingAs($me, 'sanctum')->postJson('/api/lectures', [
            'course_id'   => $course->id,
            'day_of_week' => 0,
            'start_time'  => '11:00',
            'end_time'    => '10:00',
        ])->assertStatus(422)->assertJsonValidationErrors('end_time');
    }

    public function test_the_day_must_be_a_real_weekday(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC400');

        $this->actingAs($me, 'sanctum')->postJson('/api/lectures', [
            'course_id'   => $course->id,
            'day_of_week' => 7,
            'start_time'  => '09:00',
            'end_time'    => '10:00',
        ])->assertStatus(422)->assertJsonValidationErrors('day_of_week');
    }

    public function test_a_class_cannot_be_attached_to_someone_elses_course(): void
    {
        $me    = $this->student('20230099');
        $other = $this->student('20230150', 'Maya Karim');
        $this->courseFor($me, 'CSC400');

        $this->actingAs($me, 'sanctum')->postJson('/api/lectures', [
            'course_id'   => $this->courseFor($other, 'BIO101')->id,
            'day_of_week' => 0,
            'start_time'  => '09:00',
            'end_time'    => '10:00',
        ])->assertForbidden();

        $this->assertDatabaseCount('lectures', 0);
    }

    public function test_a_student_only_sees_their_own_timetable(): void
    {
        $me    = $this->student('20230099');
        $other = $this->student('20230150', 'Maya Karim');

        $this->lectureFor($this->courseFor($me, 'CSC400'));
        $this->lectureFor($this->courseFor($other, 'BIO101'));

        $this->actingAs($me, 'sanctum')->getJson('/api/lectures')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.course.code', 'CSC400');
    }

    public function test_a_student_cannot_change_or_remove_someone_elses_class(): void
    {
        $me     = $this->student('20230099');
        $other  = $this->student('20230150', 'Maya Karim');
        $theirs = $this->lectureFor($this->courseFor($other, 'BIO101'));
        $mine   = $this->courseFor($me, 'CSC400');

        $this->actingAs($me, 'sanctum')->putJson('/api/lectures/'.$theirs->id, [
            'course_id'   => $mine->id,
            'day_of_week' => 1,
            'start_time'  => '08:00',
            'end_time'    => '09:00',
        ])->assertForbidden();

        $this->actingAs($me, 'sanctum')->deleteJson('/api/lectures/'.$theirs->id)->assertForbidden();

        $this->assertDatabaseHas('lectures', ['id' => $theirs->id, 'start_time' => '09:00']);
    }

    public function test_deleting_a_course_clears_its_classes(): void
    {
        $me      = $this->student('20230099');
        $course  = $this->courseFor($me, 'CSC400');
        $lecture = $this->lectureFor($course);

        $this->actingAs($me, 'sanctum')->deleteJson('/api/courses/'.$course->id)->assertOk();

        $this->assertDatabaseMissing('lectures', ['id' => $lecture->id]);
    }
}
