<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLectureRequest;
use App\Models\Lecture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LectureController extends Controller
{
    /*  GET /api/lectures  */

    public function index(Request $request): JsonResponse
    {
        $lectures = $request->user()
            ->lectures()
            ->with('course:id,name,code')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json($lectures);
    }

    /*  POST /api/lectures  */

    public function store(StoreLectureRequest $request): JsonResponse
    {
        $this->authorizeCourse($request, $request->course_id);

        $lecture = $request->user()->lectures()->create($request->validated());
        $lecture->load('course:id,name,code');

        return response()->json($lecture, 201);
    }

    /*  PUT /api/lectures/{lecture}  */

    public function update(StoreLectureRequest $request, Lecture $lecture): JsonResponse
    {
        $this->authorizeOwner($request, $lecture);
        $this->authorizeCourse($request, $request->course_id);

        $lecture->update($request->validated());
        $lecture->load('course:id,name,code');

        return response()->json($lecture);
    }

    /*  DELETE /api/lectures/{lecture}  */

    public function destroy(Request $request, Lecture $lecture): JsonResponse
    {
        $this->authorizeOwner($request, $lecture);
        $lecture->delete();

        return response()->json(['message' => 'Class removed.']);
    }

    /*  Helpers  */

    private function authorizeOwner(Request $request, Lecture $lecture): void
    {
        abort_if($lecture->user_id !== $request->user()->id, 403, 'Unauthorized.');
    }

    private function authorizeCourse(Request $request, int $courseId): void
    {
        $owns = $request->user()->courses()->where('id', $courseId)->exists();
        abort_if(! $owns, 403, 'This course does not belong to you.');
    }
}
