<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\Resource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $uni = 'Al Maaref University';

        $me = User::updateOrCreate(
            ['university_id' => '20230099'],
            ['full_name' => 'Ali Hadi Meselmani', 'university_name' => $uni, 'country' => 'Lebanon', 'password' => Hash::make('test1234')]
        );
        $maya = User::updateOrCreate(
            ['university_id' => '20230150'],
            ['full_name' => 'Maya Karim', 'university_name' => $uni, 'country' => 'Lebanon', 'password' => Hash::make('test1234')]
        );
        $omar = User::updateOrCreate(
            ['university_id' => '20230188'],
            ['full_name' => 'Omar Saad', 'university_name' => $uni, 'country' => 'Lebanon', 'password' => Hash::make('test1234')]
        );

        Task::query()->delete();
        Resource::query()->delete();
        Course::query()->delete();
        GroupMessage::query()->delete();
        Message::query()->delete();

        $c = fn ($u, $n, $code, $ins, $g = null, $cr = null) => Course::create([
            'user_id' => $u->id, 'name' => $n, 'code' => $code, 'instructor' => $ins,
            'semester' => 'Spring 2026', 'grade' => $g, 'credits' => $cr,
        ]);

        $web  = $c($me, 'Web Programming', 'CSC400', 'Dr. Ali Hadi', 'A-', 3);
        $dsa  = $c($me, 'Data Structures & Algorithms', 'CSC300', 'Dr. Khalil Nasr', 'B+', 4);
        $dbs  = $c($me, 'Database Systems', 'CSC310', 'Dr. Rana Fakih', null, 3);
        $math = $c($me, 'Linear Algebra', 'MTH201', 'Dr. Samir Awad', 'A', 3);
        $eng  = $c($me, 'Technical Writing', 'ENG105', 'Ms. Lara Zayn', null, 2);

        $c($maya, 'Web Programming', 'CSC400', 'Dr. Ali Hadi');
        $c($maya, 'Data Structures & Algorithms', 'CSC300', 'Dr. Khalil Nasr');
        $c($omar, 'Web Programming', 'CSC400', 'Dr. Ali Hadi');

        $t = fn ($course, $title, $type, $pri, $due, $done = false) => Task::create([
            'user_id' => $course->user_id, 'course_id' => $course->id, 'title' => $title,
            'type' => $type, 'priority' => $pri, 'due_date' => $due, 'is_completed' => $done,
        ]);

        // Dates are relative to today so the demo always looks current
        $d = fn (int $days) => now()->addDays($days)->toDateString();

        $t($web,  'Final Project — UniMate demo',   'Project',    'High',   $d(3));
        $t($web,  'Lab 6 — REST API endpoints',     'Assignment', 'Medium', $d(0));
        $t($web,  'Quiz 3 — Routing & middleware',  'Exam',       'Low',    $d(-6), true);
        $t($dsa,  'Homework 4 — Graph traversal',   'Assignment', 'High',   $d(-2));
        $t($dsa,  'Midterm Exam',                   'Exam',       'High',   $d(7));
        $t($dbs,  'ER diagram draft',               'Assignment', 'Medium', $d(2));
        $t($dbs,  'Normalization worksheet',        'Assignment', 'Low',    $d(-7), true);
        $t($math, 'Problem set 5',                  'Assignment', 'Medium', $d(5));
        $t($math, 'Quiz 2 — Eigenvalues',           'Exam',       'Medium', $d(-9), true);
        $t($eng,  'Research report outline',        'Assignment', 'Low',    $d(9));
        $t($eng,  'Peer review response',           'Assignment', 'Low',    $d(-5), true);

        $r = fn ($course, $title, $type, $val, $shared = false) => Resource::create([
            'user_id' => $course->user_id, 'course_id' => $course->id, 'title' => $title,
            'type' => $type, 'value' => $val, 'is_shared' => $shared,
        ]);

        $r($web, 'Week 3 — Routing & middleware notes', 'Note', "Routes live in routes/api.php.\nMiddleware runs before the controller — auth:sanctum protects every private endpoint.", true);
        $r($web, 'Laravel documentation', 'Link', 'https://laravel.com/docs', true);
        $r($web, 'My exam cheat sheet', 'Note', 'Private notes — Eloquent relationships, validation rules, response codes.');
        $r($dsa, 'Visualgo — graph algorithms', 'Link', 'https://visualgo.net/en/dfsbfs', true);

        $mayaWeb = Course::where('user_id', $maya->id)->where('code', 'CSC400')->first();
        $r($mayaWeb, 'Lab 5 solution walkthrough', 'Note', "Step 1: set up the routes.\nStep 2: build the controller.\nStep 3: test each endpoint with Postman.", true);
        $r($mayaWeb, 'Laravel validation rules cheat sheet', 'Link', 'https://laravel.com/docs/validation', true);

        $g = fn ($user, $body, $minsAgo) => GroupMessage::create([
            'course_code' => 'CSC400', 'university_name' => strtolower($uni),
            'sender_id' => $user->id, 'body' => $body,
            'created_at' => now()->subMinutes($minsAgo), 'updated_at' => now()->subMinutes($minsAgo),
        ]);

        $g($me,   'Welcome to the CSC400 group everyone! 👋', 180);
        $g($maya, 'Thanks! Is the final project due Friday or Monday?', 174);
        $g($omar, 'Friday the 5th — Dr. Hadi confirmed it in the last lecture.', 170);
        $g($maya, 'I shared my Lab 5 walkthrough under the course resources if anyone needs it.', 22);
        $g($me,   'Just read it, super helpful. Adding my routing notes there too.', 15);

        $dm = fn ($from, $to, $body, $minsAgo) => Message::create([
            'sender_id' => $from->id, 'recipient_id' => $to->id, 'body' => $body,
            'read_at' => now(), 'created_at' => now()->subMinutes($minsAgo), 'updated_at' => now()->subMinutes($minsAgo),
        ]);

        $dm($maya, $me, 'Hey! Did you finish Lab 6 yet?', 40);
        $dm($me, $maya, 'Almost — just testing the endpoints. Submitting tonight 👍', 36);
        $dm($maya, $me, 'Nice, good luck! Want to review the midterm together this weekend?', 30);

        $this->makeAvatar($me);
    }

    /*  Draws a simple gradient avatar so the demo account has a picture  */

    private function makeAvatar(User $user): void
    {
        if (! extension_loaded('gd')) {
            return;
        }

        $dir = storage_path('app/public/avatars');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $size = 256;
        $img  = imagecreatetruecolor($size, $size);

        for ($y = 0; $y < $size; $y++) {
            $p = $y / $size;
            imagefilledrectangle($img, 0, $y, $size, $y, imagecolorallocate(
                $img,
                (int) (79 + (147 - 79) * $p),
                (int) (70 + (51 - 70) * $p),
                (int) (229 + (234 - 229) * $p)
            ));
        }

        $letter = strtoupper(substr($user->full_name, 0, 1));
        $tmp    = imagecreatetruecolor(imagefontwidth(5), imagefontheight(5));
        imagesavealpha($tmp, true);
        imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
        imagestring($tmp, 5, 0, 0, $letter, imagecolorallocate($tmp, 255, 255, 255));

        $scale = 6;
        $w     = imagesx($tmp) * $scale;
        $h     = imagesy($tmp) * $scale;
        imagecopyresampled($img, $tmp, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0, $w, $h, imagesx($tmp), imagesy($tmp));

        imagepng($img, $dir.'/demo-avatar.png');
        $user->update(['avatar_path' => 'avatars/demo-avatar.png']);
    }
}
