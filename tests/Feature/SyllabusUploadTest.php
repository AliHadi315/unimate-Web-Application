<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The AI call itself needs a live key, so these stop at the point the request
 * hands off to the model: a 502 means the text was extracted and accepted,
 * a 422 means it was rejected before anything was sent.
 */
class SyllabusUploadTest extends TestCase
{
    use RefreshDatabase;

    private function configureAi(): void
    {
        config(['services.anthropic.key' => 'a-key', 'services.anthropic.model' => 'a-model']);
    }

    private function fixture(string $name): UploadedFile
    {
        return new UploadedFile(base_path('tests/fixtures/'.$name), $name, 'application/pdf', null, true);
    }

    public function test_text_is_extracted_from_an_uploaded_pdf(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC310');
        $this->configureAi();

        $response = $this->actingAs($me, 'sanctum')->post('/api/ai/syllabus', [
            'course_id' => $course->id,
            'file'      => $this->fixture('syllabus.pdf'),
        ], ['Accept' => 'application/json']);

        // Not a 422: the PDF yielded enough text to send on to the model
        $response->assertStatus(502);
    }

    public function test_a_pdf_with_no_text_layer_is_explained_not_sent(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC310');
        $this->configureAi();

        $this->actingAs($me, 'sanctum')->post('/api/ai/syllabus', [
            'course_id' => $course->id,
            'file'      => $this->fixture('scanned.pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJson(fn ($json) => $json->where(
                'message',
                fn ($m) => str_contains($m, 'scanned PDF')
            )->etc());
    }

    public function test_a_plain_text_file_can_be_uploaded_too(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC310');
        $this->configureAi();

        $file = UploadedFile::fake()->createWithContent(
            'syllabus.txt',
            "Course schedule\nWeek 2 (Oct 1) Assignment 1 due\nWeek 5 (Oct 20) Midterm exam"
        );

        $this->actingAs($me, 'sanctum')->post('/api/ai/syllabus', [
            'course_id' => $course->id,
            'file'      => $file,
        ], ['Accept' => 'application/json'])->assertStatus(502);
    }

    public function test_unsupported_file_types_are_refused(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC310');
        $this->configureAi();

        $this->actingAs($me, 'sanctum')->post('/api/ai/syllabus', [
            'course_id' => $course->id,
            'file'      => UploadedFile::fake()->create('syllabus.exe', 20),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_an_oversized_upload_is_refused(): void
    {
        $me     = $this->student('20230099');
        $course = $this->courseFor($me, 'CSC310');
        $this->configureAi();

        $this->actingAs($me, 'sanctum')->post('/api/ai/syllabus', [
            'course_id' => $course->id,
            'file'      => UploadedFile::fake()->create('huge.pdf', 20 * 1024),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_a_pdf_cannot_be_imported_into_someone_elses_course(): void
    {
        $me    = $this->student('20230099');
        $other = $this->student('20230150', 'Maya Karim');
        $this->configureAi();

        $this->actingAs($me, 'sanctum')->post('/api/ai/syllabus', [
            'course_id' => $this->courseFor($other, 'BIO101')->id,
            'file'      => $this->fixture('syllabus.pdf'),
        ], ['Accept' => 'application/json'])->assertNotFound();
    }
}
