<?php

namespace App\Services;

use App\Models\DocumentImport;
use App\Models\FamilyEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DocumentEventExtractionService
{
    private const IMAGE_FILE_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'];

    public function extract(DocumentImport $documentImport): array
    {
        $apiKey = config('services.openai.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY ist nicht gesetzt.');
        }

        $documentImport->loadMissing(['family.children', 'family.activeParents']);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(config('services.openai.timeout', 60))
            ->post('https://api.openai.com/v1/responses', $this->payload($documentImport));

        if ($response->failed()) {
            throw new RuntimeException('OpenAI Analyse fehlgeschlagen: '.$response->body());
        }

        $raw = $response->json();

        if (! is_array($raw)) {
            throw new RuntimeException('OpenAI Antwort ist kein gültiges JSON.');
        }

        $content = $this->responseText($raw);
        $decoded = json_decode($content, true);

        if (! is_array($decoded) || ! isset($decoded['suggestions']) || ! is_array($decoded['suggestions'])) {
            throw new RuntimeException('OpenAI Antwort konnte nicht als Terminliste gelesen werden.');
        }

        return collect($decoded['suggestions'])
            ->map(fn (array $suggestion): ?array => $this->normalizeSuggestion($suggestion, $documentImport))
            ->filter()
            ->values()
            ->all();
    }

    private function payload(DocumentImport $documentImport): array
    {
        return [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'input' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $this->userContent($documentImport),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'family_event_suggestions',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Du extrahierst Familien-Termine aus deutschsprachigen Dokumenten.
Gib nur Termine zurück, die im Dokument plausibel als konkrete Termine, Fristen, Geburtstage, Ausflüge, Elternabende, Arzttermine, Sporttermine, Ferien oder Schultermine erkennbar sind.
Nutze Europe/Zurich als Zeitzone. Wenn eine Uhrzeit fehlt, setze all_day auf true und nutze 00:00 als Startzeit.
Verwende nur die vorgegebenen Kategorien. Wenn keine Kategorie passt, verwende other.
Setze suggested_owner_type und suggested_owner_id auf die vorgegebene Zielperson, ausser das Dokument nennt eindeutig ein anderes Kind oder Elternteil aus dem Kontext.
PROMPT;
    }

    private function userContent(DocumentImport $documentImport): array
    {
        return [
            [
                'type' => 'input_text',
                'text' => $this->contextText($documentImport)."\n\nExtrahierter Dokumenttext:\n".$this->documentText($documentImport),
            ],
        ];
    }

    private function contextText(DocumentImport $documentImport): string
    {
        $family = $documentImport->family;
        $parents = $family->activeParents
            ->map(fn ($parent): array => ['id' => $parent->id, 'name' => $parent->name])
            ->values()
            ->all();
        $children = $family->children
            ->map(fn ($child): array => ['id' => $child->id, 'name' => $child->displayName()])
            ->values()
            ->all();

        return 'Heute ist '.Carbon::now('Europe/Zurich')->toDateString().'. '
            .'Familie: '.$family->name.'. '
            .'Dokumenttitel: '.$documentImport->title.'. '
            .'Standard-Ziel: '.json_encode([
                'type' => $documentImport->target_type ?? 'family',
                'id' => $documentImport->target_id,
            ], JSON_UNESCAPED_UNICODE).'. '
            .'Eltern: '.json_encode($parents, JSON_UNESCAPED_UNICODE).'. '
            .'Kinder: '.json_encode($children, JSON_UNESCAPED_UNICODE).'.';
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'suggestions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => $this->nullableString(),
                            'starts_at' => ['type' => 'string', 'format' => 'date-time'],
                            'ends_at' => $this->nullableDateTime(),
                            'all_day' => ['type' => 'boolean'],
                            'location' => $this->nullableString(),
                            'category' => [
                                'anyOf' => [
                                    ['type' => 'string', 'enum' => FamilyEvent::CATEGORIES],
                                    ['type' => 'null'],
                                ],
                            ],
                            'suggested_owner_type' => [
                                'anyOf' => [
                                    ['type' => 'string', 'enum' => ['family', 'user', 'child']],
                                    ['type' => 'null'],
                                ],
                            ],
                            'suggested_owner_id' => [
                                'anyOf' => [
                                    ['type' => 'integer'],
                                    ['type' => 'null'],
                                ],
                            ],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        ],
                        'required' => [
                            'title',
                            'description',
                            'starts_at',
                            'ends_at',
                            'all_day',
                            'location',
                            'category',
                            'suggested_owner_type',
                            'suggested_owner_id',
                            'confidence',
                        ],
                    ],
                ],
            ],
            'required' => ['suggestions'],
        ];
    }

    private function nullableString(): array
    {
        return [
            'anyOf' => [
                ['type' => 'string'],
                ['type' => 'null'],
            ],
        ];
    }

    private function nullableDateTime(): array
    {
        return [
            'anyOf' => [
                ['type' => 'string', 'format' => 'date-time'],
                ['type' => 'null'],
            ],
        ];
    }

    private function normalizeSuggestion(array $suggestion, DocumentImport $documentImport): ?array
    {
        if (blank($suggestion['title'] ?? null) || blank($suggestion['starts_at'] ?? null)) {
            return null;
        }

        $ownerType = $suggestion['suggested_owner_type'] ?? $documentImport->target_type ?? 'family';
        $ownerId = $suggestion['suggested_owner_id'] ?? $documentImport->target_id;

        if (! $this->validOwner($ownerType, $ownerId, $documentImport)) {
            $ownerType = $documentImport->target_type ?? 'family';
            $ownerId = $documentImport->target_id;
        }

        if ($ownerType === 'family') {
            $ownerId = null;
        }

        return [
            'title' => $suggestion['title'],
            'description' => $suggestion['description'] ?? null,
            'starts_at' => Carbon::parse($suggestion['starts_at'])->timezone('Europe/Zurich'),
            'ends_at' => filled($suggestion['ends_at'] ?? null) ? Carbon::parse($suggestion['ends_at'])->timezone('Europe/Zurich') : null,
            'all_day' => (bool) ($suggestion['all_day'] ?? false),
            'location' => $suggestion['location'] ?? null,
            'category' => in_array($suggestion['category'] ?? null, FamilyEvent::CATEGORIES, true) ? $suggestion['category'] : 'other',
            'suggested_owner_type' => $ownerType,
            'suggested_owner_id' => $ownerId,
            'confidence' => max(0, min(1, (float) ($suggestion['confidence'] ?? 0.5))),
        ];
    }

    private function validOwner(?string $ownerType, mixed $ownerId, DocumentImport $documentImport): bool
    {
        return match ($ownerType) {
            'family' => true,
            'user' => filled($ownerId) && $documentImport->family->activeParents->contains('id', (int) $ownerId),
            'child' => filled($ownerId) && $documentImport->family->children->contains('id', (int) $ownerId),
            default => false,
        };
    }

    private function responseText(array $raw): string
    {
        if (filled($raw['output_text'] ?? null)) {
            return $raw['output_text'];
        }

        foreach ($raw['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && filled($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI Antwort enthält keinen Text.');
    }

    private function documentText(DocumentImport $documentImport): string
    {
        $text = match ($documentImport->file_type) {
            'pdf' => $this->pdfOcrText($documentImport),
            'docx' => $this->docxText($documentImport),
            default => in_array($documentImport->file_type, self::IMAGE_FILE_TYPES, true)
                ? $this->imageOcrText($this->documentPath($documentImport))
                : throw new RuntimeException('Dieser Dateityp wird nicht unterstützt.'),
        };

        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('Aus dem Dokument konnte kein Text extrahiert werden.');
        }

        return mb_substr($text, 0, 40000);
    }

    private function pdfOcrText(DocumentImport $documentImport): string
    {
        $this->ensureCommandAvailable('pdftoppm');
        $this->ensureCommandAvailable('tesseract');

        $workDir = storage_path('app/ocr/'.pathinfo($documentImport->file_path, PATHINFO_FILENAME).'-'.uniqid());

        if (! is_dir($workDir) && ! mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
            throw new RuntimeException('OCR-Arbeitsverzeichnis konnte nicht erstellt werden.');
        }

        try {
            $prefix = $workDir.'/page';

            $this->runCommand([
                config('services.ocr.pdftoppm_path', 'pdftoppm'),
                '-r',
                (string) config('services.ocr.pdf_dpi', 200),
                '-png',
                '-f',
                '1',
                '-l',
                (string) config('services.ocr.pdf_max_pages', 10),
                $this->documentPath($documentImport),
                $prefix,
            ]);

            $pages = glob($prefix.'-*.png') ?: [];
            sort($pages);

            if ($pages === []) {
                throw new RuntimeException('PDF konnte nicht in OCR-Seitenbilder umgewandelt werden.');
            }

            return collect($pages)
                ->map(fn (string $page): string => $this->imageOcrText($page))
                ->filter()
                ->implode("\n\n");
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    private function imageOcrText(string $path): string
    {
        $this->ensureCommandAvailable('tesseract');

        return $this->runCommand([
            config('services.ocr.tesseract_path', 'tesseract'),
            $path,
            'stdout',
            '-l',
            config('services.ocr.language', 'deu+eng'),
        ]);
    }

    private function ensureCommandAvailable(string $name): void
    {
        if (! function_exists('proc_open')) {
            throw new RuntimeException('proc_open ist auf dem Server deaktiviert. OCR kann Tesseract nicht starten.');
        }

        $command = $name === 'pdftoppm'
            ? config('services.ocr.pdftoppm_path', 'pdftoppm')
            : config('services.ocr.tesseract_path', 'tesseract');

        if (blank($command)) {
            throw new RuntimeException('OCR-Befehl '.$name.' ist nicht konfiguriert.');
        }
    }

    private function runCommand(array $command): string
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('OCR-Prozess konnte nicht gestartet werden.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startedAt = time();
        $timeout = (int) config('services.ocr.timeout', 120);
        $exitCode = null;

        do {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            if (! $status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }

            if ($timeout > 0 && time() - $startedAt > $timeout) {
                proc_terminate($process);
                $exitCode = 124;
                $stderr .= 'OCR-Zeitlimit wurde überschritten.';
                break;
            }

            usleep(100000);
        } while (true);

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('OCR-Befehl fehlgeschlagen: '.trim($stderr ?: 'Unbekannter Fehler'));
        }

        return trim($stdout ?: '');
    }

    private function documentPath(DocumentImport $documentImport): string
    {
        $path = Storage::disk('public')->path($documentImport->file_path);

        if (! is_file($path)) {
            throw new RuntimeException('Dokument konnte nicht gelesen werden.');
        }

        return $path;
    }

    private function docxText(DocumentImport $documentImport): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP Zip-Erweiterung ist für DOCX-Analysen nicht verfügbar.');
        }

        $zip = new ZipArchive();
        $path = $this->documentPath($documentImport);

        if ($zip->open($path) !== true) {
            throw new RuntimeException('DOCX-Datei konnte nicht geöffnet werden.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('DOCX-Inhalt konnte nicht gelesen werden.');
        }

        $text = html_entity_decode(strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $xml)));

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $path) {
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
