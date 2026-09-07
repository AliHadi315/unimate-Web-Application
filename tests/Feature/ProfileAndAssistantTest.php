<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAndAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_update_their_details(): void
    {
        $me = $this->student('20230099');

        $this->actingAs($me, 'sanctum')->putJson('/api/auth/profile', [
            'full_name'       => 'Ali Hadi Meselmani',
            'country'         => 'Lebanon',
            'university_name' => 'Al Maaref University',
        ])->assertOk()->assertJsonPath('user.fullName', 'Ali Hadi Meselmani');

        $this->assertDatabaseHas('users', ['id' => $me->id, 'full_name' => 'Ali Hadi Meselmani']);
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $me = $this->student('20230099');

        $this->actingAs($me, 'sanctum')->putJson('/api/auth/profile', [
            'full_name'             => 'Test Student',
            'country'               => 'Lebanon',
            'university_name'       => 'Al Maaref University',
            'current_password'      => 'not-my-password',
            'password'              => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        // The old password must still work
        $this->postJson('/api/auth/login', [
            'university_id' => '20230099',
            'password'      => 'secret123',
        ])->assertOk();
    }

    public function test_a_student_can_change_their_password_with_the_right_current_one(): void
    {
        $me = $this->student('20230099');

        $this->actingAs($me, 'sanctum')->putJson('/api/auth/profile', [
            'full_name'             => 'Test Student',
            'country'               => 'Lebanon',
            'university_name'       => 'Al Maaref University',
            'current_password'      => 'secret123',
            'password'              => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'university_id' => '20230099',
            'password'      => 'brand-new-pass',
        ])->assertOk();
    }

    public function test_a_profile_picture_can_be_uploaded(): void
    {
        Storage::fake('public');
        $me = $this->student('20230099');

        $this->actingAs($me, 'sanctum')->postJson('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png'),
        ])->assertOk()->assertJsonPath('user.avatarUrl', fn ($url) => str_starts_with($url, '/storage/avatars/'));

        $this->assertCount(1, Storage::disk('public')->files('avatars'));
    }

    public function test_a_non_image_is_rejected_as_an_avatar(): void
    {
        Storage::fake('public');
        $me = $this->student('20230099');

        $this->actingAs($me, 'sanctum')->postJson('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertStatus(422)->assertJsonValidationErrors('avatar');
    }

    public function test_an_oversized_upload_is_rejected(): void
    {
        Storage::fake('public');
        $me = $this->student('20230099');

        $this->actingAs($me, 'sanctum')->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->create('huge.pdf', 20 * 1024),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_the_assistant_reports_itself_disabled_when_no_model_is_configured(): void
    {
        $me = $this->student('20230099');

        config(['services.anthropic.key' => null, 'services.anthropic.model' => null]);

        $this->actingAs($me, 'sanctum')->getJson('/api/ai/status')
            ->assertOk()->assertJsonPath('enabled', false);

        $this->actingAs($me, 'sanctum')->postJson('/api/ai/chat', [
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ])->assertStatus(503);
    }

    public function test_syllabus_import_is_unavailable_without_a_model(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC400');

        config(['services.anthropic.key' => null, 'services.anthropic.model' => null]);

        $this->actingAs($me, 'sanctum')->postJson('/api/ai/syllabus', [
            'course_id' => $course->id,
            'text'      => str_repeat('Week 1 lecture, week 2 assignment due. ', 5),
        ])->assertStatus(503);
    }

    public function test_syllabus_import_rejects_a_course_you_do_not_own(): void
    {
        $me    = $this->student('20230099');
        $other = $this->student('20230150', 'Maya Karim');

        config(['services.anthropic.key' => 'a-key', 'services.anthropic.model' => 'a-model']);

        $this->actingAs($me, 'sanctum')->postJson('/api/ai/syllabus', [
            'course_id' => $this->courseFor($other, 'BIO101')->id,
            'text'      => str_repeat('Week 1 lecture, week 2 assignment due. ', 5),
        ])->assertNotFound();
    }

    public function test_syllabus_import_needs_enough_text_to_work_with(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC400');

        config(['services.anthropic.key' => 'a-key', 'services.anthropic.model' => 'a-model']);

        $this->actingAs($me, 'sanctum')->postJson('/api/ai/syllabus', [
            'course_id' => $course->id,
            'text'      => 'too short',
        ])->assertStatus(422)
          ->assertJson(fn ($json) => $json->where(
              'message',
              fn ($m) => str_contains($m, 'Paste a bit more')
          )->etc());
    }

    public function test_the_assistant_needs_both_a_key_and_a_model(): void
    {
        $me = $this->student('20230099');

        config(['services.anthropic.key' => 'a-key', 'services.anthropic.model' => null]);

        $this->actingAs($me, 'sanctum')->getJson('/api/ai/status')
            ->assertOk()->assertJsonPath('enabled', false);
    }
}
