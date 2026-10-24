<?php

namespace Tests\Feature;

use App\Models\DocumentImport;
use App\Models\ImportedEventSuggestion;
use App\Services\DocumentEventExtractionService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class DocumentSecurityTest extends TestCase
{
    use CreatesFamily, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    public function test_upload_is_deleted_after_analysis_and_suggestions_remain_reviewable_and_importable(): void
    {
        config(['services.openai.api_key' => 'test-only']);
        $family = $this->familyFor();
        $file = UploadedFile::fake()->createWithContent('school.pdf', "%PDF-1.4\nConfidential school calendar");
        $path = $file->getPathname();
        Http::fake(['api.openai.com/*' => function ($request) use ($path) {
            $this->assertFileExists($path);
            $this->assertSame([], Storage::disk('documents')->allFiles());
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertFalse($request['store']);
            $this->assertSame('data:application/pdf;base64,'.base64_encode("%PDF-1.4\nConfidential school calendar"), $request['input'][1]['content'][1]['file_data']);

            return Http::response(['output_text' => json_encode(['suggestions' => [[
                'title' => 'Elternabend', 'description' => null, 'starts_at' => '2026-11-01T19:00:00+01:00',
                'ends_at' => null, 'all_day' => false, 'location' => null, 'category' => 'school',
                'suggested_owner_type' => 'family', 'suggested_owner_id' => null, 'confidence' => 0.9,
            ]]])]);
        }]);
        $this->actingAs($family->owner)->post("/families/$family->id/document-imports", ['title' => 'School', 'document' => $file, 'target_type' => 'family', 'analysis_consent' => '1'])->assertRedirect();
        $document = DocumentImport::firstOrFail();
        $this->assertFileDoesNotExist($path);
        $this->assertNull($document->file_path);
        $this->assertNull($document->raw_ai_result);
        $this->assertSame('analyzed', $document->status);
        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get("/families/$family->id/document-imports/$document->id")->assertOk()->assertSee('Originaldateien werden nach der Analyse gelöscht')->assertDontSee('Dokument herunterladen');
        $this->get("/document-imports/$document->id/review")->assertOk()->assertSee('Elternabend');
        $this->get("/document-imports/$document->id/download")->assertNotFound();
        $suggestion = $document->suggestions()->sole();
        $this->post("/imported-event-suggestions/$suggestion->id/accept")->assertRedirect();
        $this->assertDatabaseHas('family_events', ['family_id' => $family->id, 'title' => 'Elternabend']);
    }

    public function test_upload_rejects_executable_oversized_files_and_missing_consent(): void
    {
        $family = $this->familyFor();
        $this->actingAs($family->owner);
        $fake = UploadedFile::fake()->createWithContent('evil.pdf', '<?php echo 1;');
        $disguised = new UploadedFile($fake->getPathname(), 'evil.pdf', 'application/pdf', null, true);
        $this->post("/families/$family->id/document-imports", ['title' => 'Bad', 'target_type' => 'family', 'document' => $disguised])->assertSessionHasErrors(['document', 'analysis_consent']);
        $this->post("/families/$family->id/document-imports", ['title' => 'Large', 'target_type' => 'family', 'analysis_consent' => '1', 'document' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('document_imports', 0);
    }

    public function test_foreign_upload_target_and_malformed_id_are_rejected(): void
    {
        $family = $this->familyFor();
        $foreign = $this->familyFor();
        $data = ['title' => 'Test', 'target_type' => 'user', 'target_id' => $foreign->owner_user_id, 'analysis_consent' => '1', 'document' => UploadedFile::fake()->create('school.pdf', 1, 'application/pdf')];
        $this->actingAs($family->owner)->post("/families/$family->id/document-imports", $data)->assertSessionHasErrors('target_id');
        $data['target_id'] = ['bad'];
        $this->post("/families/$family->id/document-imports", $data)->assertSessionHasErrors('target_id');
    }

    public function test_analysis_failure_deletes_upload_and_does_not_expose_response_or_credentials(): void
    {
        $family = $this->familyFor();
        $this->mock(DocumentEventExtractionService::class)->shouldReceive('extract')->andThrow(new \RuntimeException('sk-secret-provider-response-private-name'));
        Log::spy();
        $file = UploadedFile::fake()->create('school.pdf', 1, 'application/pdf');
        $this->actingAs($family->owner)->post("/families/$family->id/document-imports", ['title' => 'Test', 'target_type' => 'family', 'analysis_consent' => '1', 'document' => $file])->assertSessionHasErrors('document');
        $this->assertFileDoesNotExist($file->getPathname());
        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertStringNotContainsString('sk-secret', json_encode(session()->all()));
        $this->assertDatabaseHas('document_imports', ['status' => 'failed', 'raw_ai_result' => null, 'file_path' => null]);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => ! str_contains(json_encode($context), 'sk-secret'))->once();
    }

    public function test_upload_is_deleted_even_if_import_record_cannot_be_created(): void
    {
        $family = $this->familyFor();
        DocumentImport::creating(fn () => throw new \RuntimeException('Database unavailable'));
        $file = UploadedFile::fake()->create('school.pdf', 1, 'application/pdf');
        $this->actingAs($family->owner)->post("/families/$family->id/document-imports", ['title' => 'School', 'document' => $file, 'target_type' => 'family', 'analysis_consent' => '1'])->assertStatus(500);
        $this->assertFileDoesNotExist($file->getPathname());
        $this->assertDatabaseCount('document_imports', 0);
        Http::assertNothingSent();
    }

    public function test_failed_suggestion_save_rolls_back_and_deletes_upload(): void
    {
        $family = $this->familyFor();
        $suggestion = ['title' => 'School', 'starts_at' => '2026-11-01 19:00:00', 'all_day' => false, 'category' => 'school', 'suggested_owner_type' => 'family'];
        $this->mock(DocumentEventExtractionService::class)->shouldReceive('extract')->once()->andReturn([$suggestion, $suggestion]);
        $attempts = 0;
        ImportedEventSuggestion::creating(function () use (&$attempts) {
            if (++$attempts === 2) {
                throw new \RuntimeException('Save failed');
            }
        });
        $file = UploadedFile::fake()->create('school.pdf', 1, 'application/pdf');
        $this->actingAs($family->owner)->post("/families/$family->id/document-imports", ['title' => 'School', 'document' => $file, 'target_type' => 'family', 'analysis_consent' => '1'])->assertSessionHasErrors('document');
        $this->assertFileDoesNotExist($file->getPathname());
        $this->assertDatabaseCount('imported_event_suggestions', 0);
        $this->assertDatabaseHas('document_imports', ['status' => 'failed', 'file_path' => null]);
    }

    public function test_upload_is_deleted_even_if_recording_analysis_failure_also_fails(): void
    {
        $family = $this->familyFor();
        $this->mock(DocumentEventExtractionService::class)->shouldReceive('extract')->once()->andThrow(new \RuntimeException('Analysis failed'));
        DocumentImport::updating(fn () => throw new \RuntimeException('Database unavailable'));
        $file = UploadedFile::fake()->create('school.pdf', 1, 'application/pdf');
        $this->actingAs($family->owner)->post("/families/$family->id/document-imports", ['title' => 'School', 'document' => $file, 'target_type' => 'family', 'analysis_consent' => '1'])->assertStatus(500);
        $this->assertFileDoesNotExist($file->getPathname());
    }

    public function test_purge_deletes_legacy_originals_without_migration_and_preserves_calendar_data(): void
    {
        $family = $this->familyFor();
        $document = $this->documentFor($family);
        $document->update(['file_path' => 'document-imports/old.pdf', 'raw_ai_result' => ['private' => 'old result']]);
        $suggestion = $this->suggestionFor($document);
        $this->actingAs($family->owner)->post("/imported-event-suggestions/$suggestion->id/accept")->assertRedirect();
        Storage::disk('public')->put('document-imports/old.pdf', 'legacy private bytes');
        Storage::disk('public')->put('document-imports/orphan/old.pdf', 'orphaned bytes');
        Storage::disk('documents')->put('document-imports/old.enc', 'encrypted bytes');
        Storage::disk('public')->put('unrelated.txt', 'keep');
        $this->artisan('documents:purge', ['--dry-run' => true])->expectsOutput('3 Originaldateien zum Löschen gefunden.')->assertSuccessful();
        Storage::disk('public')->assertExists('document-imports/old.pdf');
        $this->assertNotNull($document->fresh()->file_path);
        $this->assertNotNull($document->fresh()->raw_ai_result);
        $this->artisan('documents:purge')->assertSuccessful();
        $this->assertSame([], Storage::disk('public')->allFiles('document-imports'));
        $this->assertSame([], Storage::disk('documents')->allFiles('document-imports'));
        Storage::disk('public')->assertExists('unrelated.txt');
        $this->assertNull($document->fresh()->file_path);
        $this->assertNull($document->fresh()->raw_ai_result);
        $this->assertSame('imported', $suggestion->fresh()->status);
        $this->assertDatabaseCount('family_events', 1);
        $this->artisan('documents:purge')->expectsOutput('0 Originaldateien gelöscht. Vorschläge und Termine bleiben erhalten.')->assertSuccessful();
    }

    public function test_purge_succeeds_for_missing_originals_and_never_follows_database_paths(): void
    {
        $document = $this->documentFor($this->familyFor());
        $document->update(['file_path' => '../unrelated.txt']);
        Storage::disk('public')->put('unrelated.txt', 'keep');
        $this->artisan('documents:purge')->assertSuccessful();
        Storage::disk('public')->assertExists('unrelated.txt');
        $this->assertNull($document->fresh()->file_path);
    }

    public function test_purge_reports_delete_failure_and_keeps_metadata_for_retry(): void
    {
        $document = $this->documentFor($this->familyFor());
        $document->update(['file_path' => 'document-imports/old.pdf']);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('allFiles')->with('document-imports')->once()->andReturn(['document-imports/old.pdf']);
        $disk->shouldReceive('delete')->with('document-imports/old.pdf')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $this->artisan('documents:purge')->assertFailed();
        $this->assertSame('document-imports/old.pdf', $document->fresh()->file_path);
    }

    public function test_family_deletion_works_without_retained_originals(): void
    {
        $family = $this->familyFor();
        $document = $this->documentFor($family);
        $this->actingAs($family->owner)->delete("/families/$family->id", ['current_password' => 'password'])->assertRedirect();
        $this->assertDatabaseMissing('document_imports', ['id' => $document->id]);
    }

    public function test_image_extraction_reads_temporary_upload_without_persisting_it(): void
    {
        config(['services.openai.api_key' => 'test-only']);
        $document = $this->documentFor($this->familyFor());
        $document->update(['file_type' => 'png']);
        $file = UploadedFile::fake()->createWithContent('photo.png', 'image bytes');
        Http::fake(['api.openai.com/*' => Http::response(['output_text' => '{"suggestions":[]}'])]);
        Log::spy();
        $this->assertSame([], app(DocumentEventExtractionService::class)->extract($document, $file));
        Http::assertSent(fn ($request) => $request['store'] === false && $request['input'][1]['content'][1]['image_url'] === 'data:image/png;base64,'.base64_encode('image bytes'));
        $this->assertSame([], Storage::disk('documents')->allFiles());
        Log::shouldNotHaveReceived('info');
    }

    public function test_slashes_in_titles_do_not_discard_the_upload_or_split_suggestions(): void
    {
        config(['services.openai.api_key' => 'test-only']);
        $family = $this->familyFor();
        $titles = array_merge(array_fill(0, 9, 'Schulanlass'), ['Elternabend / Informationsabend', 'Sporttag / Verschiebedatum']);
        $suggestions = array_map(fn ($title) => [
            'title' => $title, 'description' => null, 'starts_at' => '2026-11-01T19:00:00+01:00',
            'ends_at' => null, 'all_day' => false, 'location' => null, 'category' => 'school',
            'suggested_owner_type' => 'family', 'suggested_owner_id' => null, 'confidence' => 0.9,
        ], $titles);
        Http::fake(['api.openai.com/*' => Http::response(['output_text' => json_encode(['suggestions' => $suggestions])])]);
        $file = UploadedFile::fake()->createWithContent('school.pdf', '%PDF-1.4 synthetic test');
        $this->actingAs($family->owner)->post("/families/$family->id/document-imports", ['title' => 'School', 'document' => $file, 'target_type' => 'family', 'analysis_consent' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $document = DocumentImport::sole();
        $this->assertSame('analyzed', $document->status);
        $this->assertSame($titles, $document->suggestions()->orderBy('id')->pluck('title')->all());
        $this->assertSame(11, $document->suggestions()->where('status', 'pending')->count());
        $this->assertDatabaseCount('family_events', 0);
        $this->assertFileDoesNotExist($file->getPathname());
        $this->assertNull($document->file_path);
        $this->assertNull($document->raw_ai_result);
        Http::assertSentCount(1);
    }

    public function test_slash_title_does_not_bypass_category_validation(): void
    {
        config(['services.openai.api_key' => 'test-only']);
        $document = $this->documentFor($this->familyFor());
        $file = UploadedFile::fake()->createWithContent('school.pdf', '%PDF-1.4 synthetic test');
        Http::fake(['api.openai.com/*' => Http::response(['output_text' => json_encode(['suggestions' => [[
            'title' => 'Elternabend / Informationsabend', 'description' => null, 'starts_at' => '2026-11-01T19:00:00+01:00',
            'ends_at' => null, 'all_day' => false, 'location' => null, 'category' => 'invalid',
            'suggested_owner_type' => 'family', 'suggested_owner_id' => null, 'confidence' => 0.9,
        ]]])])]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('category ist nicht erlaubt');
        app(DocumentEventExtractionService::class)->extract($document, $file);
    }

    public function test_malformed_ai_output_is_rejected(): void
    {
        config(['services.openai.api_key' => 'test-only']);
        $document = $this->documentFor($this->familyFor());
        $file = UploadedFile::fake()->createWithContent('school.pdf', '%PDF-1.4 private');
        Http::fake(['api.openai.com/*' => Http::response(['output_text' => '{"suggestions":[{"title":"Missing fields"}]}'])]);
        $this->expectException(ValidationException::class);
        app(DocumentEventExtractionService::class)->extract($document, $file);
    }

    public function test_docx_text_is_extracted_from_temporary_upload_with_bounded_archive_read(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('zip extension required');
        }
        config(['services.openai.api_key' => 'test-only']);
        $document = $this->documentFor($this->familyFor());
        $document->update(['file_type' => 'docx']);
        $temp = tempnam(sys_get_temp_dir(), 'docx-test');
        try {
            $zip = new \ZipArchive;
            $zip->open($temp, \ZipArchive::OVERWRITE);
            $zip->addFromString('word/document.xml', '<w:document><w:p>Elternabend am 1. November</w:p></w:document>');
            $zip->close();
            $file = UploadedFile::fake()->createWithContent('school.docx', file_get_contents($temp));
        } finally {
            unlink($temp);
        }
        Http::fake(['api.openai.com/*' => Http::response(['output_text' => '{"suggestions":[]}'])]);
        $this->assertSame([], app(DocumentEventExtractionService::class)->extract($document, $file));
        Http::assertSent(fn ($request) => str_contains($request['input'][1]['content'][1]['text'], 'Elternabend am 1. November'));
    }

    public function test_docx_zip_bomb_is_rejected_before_provider_request(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('zip extension required');
        }
        config(['services.openai.api_key' => 'test-only']);
        $document = $this->documentFor($this->familyFor());
        $document->update(['file_type' => 'docx']);
        $temp = tempnam(sys_get_temp_dir(), 'docx-test');
        try {
            $zip = new \ZipArchive;
            $zip->open($temp, \ZipArchive::OVERWRITE);
            $zip->addFromString('word/document.xml', str_repeat('x', 2 * 1024 * 1024 + 1));
            $zip->close();
            $file = UploadedFile::fake()->createWithContent('school.docx', file_get_contents($temp));
        } finally {
            unlink($temp);
        }
        try {
            app(DocumentEventExtractionService::class)->extract($document, $file);
            $this->fail('Oversized DOCX must be rejected');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('zu gross', $exception->getMessage());
        }
        Http::assertNothingSent();
    }
}
