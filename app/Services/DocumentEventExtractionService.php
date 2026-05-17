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
    private const IMAGE_MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
    ];

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
        $content = [
            [
                'type' => 'input_text',
                'text' => $this->contextText($documentImport),
            ],
        ];

        if ($documentImport->file_type === 'pdf') {
            $content[] = [
                'type' => 'input_file',
                'filename' => $documentImport->original_filename,
                'file_data' => 'data:application/pdf;base64,'.base64_encode($this->fileContents($documentImport)),
            ];

            return $content;
        }

        if (in_array($documentImport->file_type, self::IMAGE_FILE_TYPES, true)) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => $this->imageDataUrl($documentImport),
            ];

            return $content;
        }

        $content[] = [
            'type' => 'input_text',
            'text' => "DOCX-Inhalt:\n".$this->docxText($documentImport),
        ];

        return $content;
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

    private function documentPath(DocumentImport $documentImport): string
    {
        $path = Storage::disk('public')->path($documentImport->file_path);

        if (! is_file($path)) {
            throw new RuntimeException('Dokument konnte nicht gelesen werden.');
        }

        return $path;
    }

    private function fileContents(DocumentImport $documentImport): string
    {
        $contents = file_get_contents($this->documentPath($documentImport));

        if ($contents === false) {
            throw new RuntimeException('Dokument konnte nicht gelesen werden.');
        }

        return $contents;
    }

    private function imageDataUrl(DocumentImport $documentImport): string
    {
        $mimeType = self::IMAGE_MIME_TYPES[$documentImport->file_type] ?? 'application/octet-stream';

        return 'data:'.$mimeType.';base64,'.base64_encode($this->fileContents($documentImport));
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
}
