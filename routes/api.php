<?php

use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatSessionController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\GroupChatController;
use App\Http\Controllers\Api\LectureController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::apiResource('courses',        CourseController::class);
    Route::apiResource('tasks',          TaskController::class);
    Route::apiResource('lectures',       LectureController::class);
    Route::apiResource('resources',      ResourceController::class);
    Route::apiResource('chat-sessions',  ChatSessionController::class);
    Route::patch('tasks/{task}/toggle',  [TaskController::class, 'toggle']);
    Route::get('ai/status',              [AiChatController::class, 'status']);
    Route::post('ai/chat',               [AiChatController::class, 'chat']);
    Route::post('ai/syllabus',           [AiChatController::class, 'syllabus']);
    Route::put('auth/profile',           [ProfileController::class, 'update']);
    Route::post('auth/avatar',           [ProfileController::class, 'avatar']);
    Route::post('uploads',               [UploadController::class, 'store']);
    Route::get('chat/contacts',          [MessageController::class, 'contacts']);
    Route::get('chat/messages/{user}',   [MessageController::class, 'conversation']);
    Route::post('chat/messages/{user}',  [MessageController::class, 'send']);
    Route::get('groups',                 [GroupChatController::class, 'index']);
    Route::get('groups/{code}/messages', [GroupChatController::class, 'show']);
    Route::post('groups/{code}/messages',[GroupChatController::class, 'store']);
    Route::get('shared-resources',       [ResourceController::class, 'shared']);
});