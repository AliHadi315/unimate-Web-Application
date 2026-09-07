<?php

use Illuminate\Support\Facades\Route;

/*
 * The frontend is plain HTML/JS in public/. These routes serve each page
 * on a clean URL (e.g. /dashboard instead of /pages/dashboard.html).
 */

$pages = [
    '/'           => 'index.html',
    '/login'      => 'pages/login.html',
    '/register'   => 'pages/register.html',
    '/dashboard'  => 'pages/dashboard.html',
    '/courses'    => 'pages/courses.html',
    '/tasks'      => 'pages/tasks.html',
    '/timetable'  => 'pages/timetable.html',
    '/statistics' => 'pages/statistics.html',
    '/ai'         => 'pages/ai.html',
    '/messages'   => 'pages/messages.html',
    '/profile'    => 'pages/profile.html',
];

foreach ($pages as $uri => $file) {
    $route = Route::get($uri, fn () => response()->file(public_path($file)));

    if ($uri === '/login') {
        $route->name('login'); // where unauthenticated web requests get redirected
    }
}
