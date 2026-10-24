<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use CreatesFamily, RefreshDatabase;

    public function test_readiness_rejects_unsafe_configuration_and_legacy_public_documents(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        Storage::disk('public')->put('document-imports/old.pdf', 'private');
        config(['app.debug' => true]);
        $this->artisan('security:check')->expectsOutput('FEHLER: Produktionsmodus ohne Debug')->expectsOutput('FEHLER: Keine öffentlichen Dokumente')->assertFailed();
    }

    public function test_production_readiness_accepts_complete_configuration(): void
    {
        if (! in_array('argon2id', password_algos(), true) || ! extension_loaded('zip')) {
            $this->markTestSkipped('Production extensions required');
        }
        Storage::fake('public');
        Storage::fake('documents');
        $this->documentFor($this->familyFor());
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://family.example.org', 'session.driver' => 'file', 'session.secure' => true, 'cache.default' => 'file', 'hashing.driver' => 'argon2id', 'security.registration_enabled' => false]);
        $this->artisan('security:check')->assertSuccessful();
    }

    public function test_readiness_rejects_private_originals_and_stale_document_metadata(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        Storage::disk('documents')->put('document-imports/old.enc', 'old contents');
        $document = $this->documentFor($this->familyFor());
        $document->update(['file_path' => 'document-imports/old.enc', 'raw_ai_result' => ['private' => 'old response']]);
        $this->artisan('security:check')
            ->expectsOutput('FEHLER: Keine privat aufbewahrten Originaldateien')
            ->expectsOutput('FEHLER: Keine gespeicherten KI-Rohantworten')
            ->expectsOutput('FEHLER: Keine dauerhaften Dokumentpfade')
            ->assertFailed();
    }

    public function test_production_errors_do_not_log_sensitive_exception_messages(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://family.example.org']);
        Log::spy();
        Route::get('/testing-error', fn () => throw new \RuntimeException('private-secret-token'))->name('testing.error');
        $this->get('https://family.example.org/testing-error')->assertStatus(500)->assertDontSee('private-secret-token');
        Log::shouldHaveReceived('error')->once()->with('Application error', ['exception_class' => \RuntimeException::class, 'route' => null]);
    }
}
