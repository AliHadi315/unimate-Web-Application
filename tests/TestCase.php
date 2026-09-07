<?php

namespace Tests;

use App\Models\Course;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    /*  Small builders so tests read as scenarios instead of setup  */

    protected function student(string $universityId, string $name = 'Test Student', string $university = 'Al Maaref University'): User
    {
        return User::create([
            'full_name'       => $name,
            'university_id'   => $universityId,
            'university_name' => $university,
            'country'         => 'Lebanon',
            'password'        => Hash::make('secret123'),
        ]);
    }

    protected function courseFor(User $user, string $code, array $extra = []): Course
    {
        return $user->courses()->create($extra + [
            'name'       => $code.' Course',
            'code'       => $code,
            'instructor' => 'Dr. Test',
            'semester'   => 'Spring 2026',
        ]);
    }

    protected function taskFor(Course $course, array $extra = []): Task
    {
        return Task::create($extra + [
            'user_id'   => $course->user_id,
            'course_id' => $course->id,
            'title'     => 'Test task',
            'type'      => 'Assignment',
            'priority'  => 'Medium',
            'due_date'  => '2026-09-10',
        ]);
    }
}
