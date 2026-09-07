<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedResourceTest extends TestCase
{
    use RefreshDatabase;

    private function resourceIn(Course $course, string $title, bool $shared): Resource
    {
        return Resource::create([
            'user_id'   => $course->user_id,
            'course_id' => $course->id,
            'title'     => $title,
            'type'      => 'Note',
            'value'     => 'Some study notes',
            'is_shared' => $shared,
        ]);
    }

    public function test_a_classmate_sees_resources_shared_for_the_same_course_code(): void
    {
        $me   = $this->student('20230099');
        $maya = $this->student('20230150', 'Maya Karim');

        $myCourse = $this->courseFor($me, 'CSC400');
        $this->resourceIn($this->courseFor($maya, 'CSC400'), 'Lab 5 walkthrough', shared: true);

        $this->actingAs($me, 'sanctum')->getJson('/api/shared-resources?course_id='.$myCourse->id)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Lab 5 walkthrough')
            ->assertJsonPath('0.owner_name', 'Maya Karim');
    }

    public function test_private_resources_are_never_shared(): void
    {
        $me   = $this->student('20230099');
        $maya = $this->student('20230150', 'Maya Karim');

        $myCourse = $this->courseFor($me, 'CSC400');
        $this->resourceIn($this->courseFor($maya, 'CSC400'), 'Maya private draft', shared: false);

        $this->actingAs($me, 'sanctum')->getJson('/api/shared-resources?course_id='.$myCourse->id)
            ->assertOk()->assertJsonCount(0);
    }

    public function test_students_at_a_different_university_are_not_classmates(): void
    {
        $me      = $this->student('20230099');
        $outside = $this->student('99', 'Other Uni Student', 'Some Other University');

        $myCourse = $this->courseFor($me, 'CSC400');
        $this->resourceIn($this->courseFor($outside, 'CSC400'), 'Outside notes', shared: true);

        $this->actingAs($me, 'sanctum')->getJson('/api/shared-resources?course_id='.$myCourse->id)
            ->assertOk()->assertJsonCount(0);
    }

    public function test_a_different_course_code_is_not_shared(): void
    {
        $me   = $this->student('20230099');
        $maya = $this->student('20230150', 'Maya Karim');

        $myCourse = $this->courseFor($me, 'CSC400');
        $this->resourceIn($this->courseFor($maya, 'BIO101'), 'Biology notes', shared: true);

        $this->actingAs($me, 'sanctum')->getJson('/api/shared-resources?course_id='.$myCourse->id)
            ->assertOk()->assertJsonCount(0);
    }

    public function test_your_own_resources_are_not_listed_as_shared_by_classmates(): void
    {
        $me       = $this->student('20230099');
        $myCourse = $this->courseFor($me, 'CSC400');
        $this->resourceIn($myCourse, 'My own shared note', shared: true);

        $this->actingAs($me, 'sanctum')->getJson('/api/shared-resources?course_id='.$myCourse->id)
            ->assertOk()->assertJsonCount(0);
    }

    public function test_you_cannot_read_shared_resources_for_a_course_you_do_not_take(): void
    {
        $me         = $this->student('20230099');
        $maya       = $this->student('20230150', 'Maya Karim');
        $theirCourse = $this->courseFor($maya, 'CSC400');

        $this->actingAs($me, 'sanctum')->getJson('/api/shared-resources?course_id='.$theirCourse->id)
            ->assertNotFound();
    }
}
