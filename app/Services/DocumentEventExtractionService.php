<?php

namespace App\Services;

use App\Models\DocumentImport;
use App\Models\FamilyEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        Log::info('OpenAI document extraction raw response', [
            'document_import_id' => $documentImport->id,
            'response' => $raw,
        ]);

        $content = $this->responseText($raw);
        $decoded = json_decode($content, true);

        if (! is_array($decoded) || ! isset($decoded['suggestions']) || ! is_array($decoded['suggestions'])) {
            throw new RuntimeException('OpenAI Antwort konnte nicht als Terminliste gelesen werden.');
        }

        $this->validateDecodedResponse($decoded, $documentImport);

        return collect($decoded['suggestions'])
            ->map(fn (array $suggestion): ?array => $this->normalizeSuggestion($suggestion, $documentImport))
            ->filter()
            ->values()
            ->all();
    }

    private function payload(DocumentImport $documentImport): array
    {
        return [
            'model' => config('services.openai.model', 'gpt-5.4'),
            'temperature' => 0,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->systemPrompt(),
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => $this->userContent($documentImport),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'family_calendar_suggestions',
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
Analysiere das angehängte PDF/Bild direkt visuell. Verwende kein Markdown.
Prüfe jede sichtbare Zeile separat und extrahiere alle sichtbaren Termine.
Führe keine Termine zusammen. Wenn eine Zeile zwei unabhängige Ereignisse enthält, erzeuge zwei separate Termine.
Zusammengehörige Ferien- oder Feiertagsbezeichnungen wie "Karfreitag / Ostern" bleiben ein einzelner mehrtägiger Termin.
Wenn eine Zeile nur Informationstext ohne konkreten Termin enthält, ignoriere sie.
Nutze Europe/Zurich als Zeitzone.
Wenn eine Uhrzeit fehlt: all_day=true und starts_at auf 00:00 setzen.
Mehrtägige all_day-Termine müssen ends_at exklusiv setzen: letzter sichtbarer Tag + 1 Tag um 00:00.
Verwende nur diese Kategorien: school, holiday, birthday, excursion, parent_evening, doctor, sports, deadline, other.
Setze suggested_owner_type und suggested_owner_id standardmässig auf die vorgegebene Zielperson.
Ändere suggested_owner_type und suggested_owner_id nur, wenn das Dokument eindeutig eine bekannte Person aus der Familie nennt.
Geburtstage fremder Kinder oder fremder Personen nicht als Owner setzen; beim Standard-Ziel belassen.
Gib exakt JSON im Format {"suggestions":[...]} zurück.
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
            .'Standard-Ziel: '.json_encode($this->targetContext($documentImport), JSON_UNESCAPED_UNICODE).'. '
            .'Eltern: '.json_encode($parents, JSON_UNESCAPED_UNICODE).'. '
            .'Kinder: '.json_encode($children, JSON_UNESCAPED_UNICODE).'. '
            .'Extrahiere alle Termine aus dem angehängten Dokument.';
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
                                'type' => 'string',
                                'enum' => FamilyEvent::CATEGORIES,
                            ],
                            'suggested_owner_type' => [
                                'type' => 'string',
                                'enum' => ['family', 'parent', 'child'],
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

        $ownerType = $this->ownerTypeForApp($suggestion['suggested_owner_type'] ?? null) ?? $documentImport->target_type ?? 'family';
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

    private function targetContext(DocumentImport $documentImport): array
    {
        return [
            'type' => $this->ownerTypeForModel($documentImport->target_type ?? 'family'),
            'id' => $documentImport->target_id,
            'name' => $documentImport->targetDisplayName(),
        ];
    }

    private function ownerTypeForModel(?string $ownerType): string
    {
        return match ($ownerType) {
            'user' => 'parent',
            'child' => 'child',
            default => 'family',
        };
    }

    private function ownerTypeForApp(?string $ownerType): ?string
    {
        return match ($ownerType) {
            'parent' => 'user',
            'child' => 'child',
            'family' => 'family',
            default => null,
        };
    }

    private function validateDecodedResponse(array $decoded, DocumentImport $documentImport): void
    {
        $errors = [];

        foreach ($decoded['suggestions'] as $index => $suggestion) {
            if (! is_array($suggestion)) {
                $errors[] = "suggestions.$index ist kein Objekt.";
                continue;
            }

            foreach (['title', 'description', 'starts_at', 'ends_at', 'all_day', 'location', 'category', 'suggested_owner_type', 'suggested_owner_id', 'confidence'] as $field) {
                if (! array_key_exists($field, $suggestion)) {
                    $errors[] = "suggestions.$index.$field fehlt.";
                }
            }

            if (blank($suggestion['starts_at'] ?? null)) {
                $errors[] = "suggestions.$index.starts_at darf nicht leer sein.";
            }

            if (($suggestion['all_day'] ?? false) === true && filled($suggestion['starts_at'] ?? null)) {
                $startsAt = Carbon::parse($suggestion['starts_at'])->timezone('Europe/Zurich');

                if ($startsAt->hour !== 0 || $startsAt->minute !== 0 || $startsAt->second !== 0) {
                    $errors[] = "suggestions.$index.starts_at muss bei all_day=true auf 00:00 stehen.";
                }
            }

            if (filled($suggestion['title'] ?? null) && $this->looksLikeCombinedTitle($suggestion['title'])) {
                $errors[] = "suggestions.$index.title wirkt kombiniert und muss als separate Events modelliert werden.";
            }

            if (! in_array($suggestion['category'] ?? null, FamilyEvent::CATEGORIES, true)) {
                $errors[] = "suggestions.$index.category ist nicht erlaubt.";
            }

            if (! in_array($suggestion['suggested_owner_type'] ?? null, ['family', 'parent', 'child'], true)) {
                $errors[] = "suggestions.$index.suggested_owner_type ist nicht erlaubt.";
            }
        }

        if ($errors === []) {
            return;
        }

        Log::warning('OpenAI document extraction validation failed', [
            'document_import_id' => $documentImport->id,
            'errors' => $errors,
            'decoded' => $decoded,
        ]);

        throw new RuntimeException('OpenAI Antwort hat die Validierung nicht bestanden: '.implode(' ', $errors));
    }

    private function looksLikeCombinedTitle(string $title): bool
    {
        if (! preg_match('/\s+\/\s+/', $title)) {
            return false;
        }

        $normalized = mb_strtolower(trim($title));
        $allowedCompoundTitles = [
            'karfreitag / ostern',
        ];

        if (in_array($normalized, $allowedCompoundTitles, true)) {
            return false;
        }

        $holidayTerms = ['ferien', 'feiertag', 'ostern', 'pfingsten', 'weihnachten', 'karfreitag'];

        foreach ($holidayTerms as $term) {
            if (str_contains($normalized, $term)) {
                return false;
            }
        }

        return true;
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
