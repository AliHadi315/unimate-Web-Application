<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'full_name'             => 'Ali Hadi Meselmani',
            'university_id'         => '20230099',
            'university_name'       => 'Al Maaref University',
            'country'               => 'Lebanon',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.fullName', 'Ali Hadi Meselmani')
            ->assertJsonStructure(['user' => ['id', 'universityId', 'avatarUrl'], 'token']);

        $this->assertDatabaseHas('users', ['university_id' => '20230099']);
    }

    public function test_the_password_is_never_returned_or_stored_in_plain_text(): void
    {
        $this->postJson('/api/auth/register', [
            'full_name'             => 'Ali Hadi Meselmani',
            'university_id'         => '20230099',
            'university_name'       => 'Al Maaref University',
            'country'               => 'Lebanon',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated()->assertJsonMissingPath('user.password');

        $this->assertNotSame('secret123', User::first()->password);
    }

    public function test_a_university_id_cannot_be_registered_twice(): void
    {
        $this->student('20230099');

        $this->postJson('/api/auth/register', [
            'full_name'             => 'Someone Else',
            'university_id'         => '20230099',
            'university_name'       => 'Al Maaref University',
            'country'               => 'Lebanon',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors('university_id');
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/auth/register', [
            'full_name'             => 'Ali Hadi Meselmani',
            'university_id'         => '20230099',
            'university_name'       => 'Al Maaref University',
            'country'               => 'Lebanon',
            'password'              => 'secret123',
            'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_a_student_can_log_in_with_the_right_password(): void
    {
        $this->student('20230099');

        $this->postJson('/api/auth/login', [
            'university_id' => '20230099',
            'password'      => 'secret123',
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_the_wrong_password(): void
    {
        $this->student('20230099');

        $this->postJson('/api/auth/login', [
            'university_id' => '20230099',
            'password'      => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('university_id');
    }

    public function test_private_endpoints_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/courses')->assertUnauthorized();
        $this->getJson('/api/tasks')->assertUnauthorized();
    }

    public function test_a_real_token_authenticates_and_logout_revokes_it(): void
    {
        $this->student('20230099');

        $token = $this->postJson('/api/auth/login', [
            'university_id' => '20230099',
            'password'      => 'secret123',
        ])->json('token');

        $auth = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/auth/me', $auth)->assertOk()->assertJsonPath('user.universityId', '20230099');

        $this->postJson('/api/auth/logout', [], $auth)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The guard memoises the resolved user within a single test process
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/me', $auth)->assertUnauthorized();
    }
}
