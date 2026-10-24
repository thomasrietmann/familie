<?php

namespace App\Console\Commands;

use App\Models\DocumentImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CheckSecurity extends Command
{
    protected $signature = 'security:check';

    protected $description = 'Produktionskonfiguration, Build und Dokumentzugriff vor der Freigabe prüfen';

    public function handle(): int
    {
        $checks = [
            'Produktionsmodus ohne Debug' => app()->isProduction() && ! config('app.debug'),
            'HTTPS und feste Domain' => config('security.force_https') && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https' && filled(parse_url(config('app.url'), PHP_URL_HOST)),
            'Verschlüsselte Datei-Sessions mit sicheren Cookies' => config('session.driver') === 'file' && config('session.encrypt') && config('session.secure') && config('session.http_only') && config('session.same_site') === 'lax' && config('session.serialization') === 'json',
            'Dauerhafter Rate-Limit-Speicher' => in_array(config('cache.default'), ['file', 'database'], true),
            'Argon2id verfügbar und aktiviert' => config('hashing.driver') === 'argon2id' && in_array('argon2id', password_algos(), true),
            'Kein Vite-Entwicklungsserver' => ! is_file(public_path('hot')),
            'Registrierung geschlossen oder reCAPTCHA konfiguriert' => ! config('security.registration_enabled') || (config('services.recaptcha.enabled') && filled(config('services.recaptcha.site_key')) && filled(config('services.recaptcha.secret_key')) && config('services.recaptcha.expected_hostname') === parse_url(config('app.url'), PHP_URL_HOST)),
        ];
        try {
            $encrypted = Crypt::encryptString('readiness');
            $checks['APP_KEY für Verschlüsselung verwendbar'] = Crypt::decryptString($encrypted) === 'readiness';
        } catch (Throwable) {
            $checks['APP_KEY für Verschlüsselung verwendbar'] = false;
        }
        foreach (['fileinfo', 'openssl', 'mbstring', 'dom', 'pdo_mysql', 'zip'] as $extension) {
            $checks['PHP-Erweiterung '.$extension] = extension_loaded($extension);
        }
        try {
            if (! is_file(public_path('build/manifest.json'))) {
                throw new \RuntimeException('Build fehlt');
            }
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
            $checks['Vollständiger Frontend-Build'] = collect(['resources/css/app.css', 'resources/js/app.js'])->every(fn ($entry) => isset($manifest[$entry]['file']) && is_file(public_path('build/'.$manifest[$entry]['file'])));
        } catch (Throwable) {
            $checks['Vollständiger Frontend-Build'] = false;
        }
        try {
            DB::select('select 1');
            $checks['Datenbank und Audit-Migration'] = Schema::hasTable('audit_logs');
            $checks['Keine öffentlichen Dokumente'] = collect(Storage::disk('public')->allFiles('document-imports'))->reject(fn ($path) => basename($path) === '.gitignore')->isEmpty();
            $checks['Keine privat aufbewahrten Originaldateien'] = collect(Storage::disk('documents')->allFiles('document-imports'))->reject(fn ($path) => basename($path) === '.gitignore')->isEmpty();
            $checks['Keine gespeicherten KI-Rohantworten'] = ! DocumentImport::whereNotNull('raw_ai_result')->exists();
            $checks['Keine dauerhaften Dokumentpfade'] = ! DocumentImport::whereNotNull('file_path')->exists();
        } catch (Throwable) {
            $checks['Datenbank und Dokumentreste prüfbar'] = false;
        }
        foreach ($checks as $label => $passed) {
            $passed ? $this->info('OK: '.$label) : $this->error('FEHLER: '.$label);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
