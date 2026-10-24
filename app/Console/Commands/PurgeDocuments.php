<?php

namespace App\Console\Commands;

use App\Models\DocumentImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PurgeDocuments extends Command
{
    protected $signature = 'documents:purge {--dry-run : Nur Anzahl der Originaldateien prüfen}';

    protected $description = 'Alle vorhandenen Originaldateien und KI-Rohantworten endgültig löschen; Vorschläge und Termine behalten';

    public function handle(): int
    {
        $count = 0;
        try {
            foreach (['public', 'documents'] as $diskName) {
                $disk = Storage::disk($diskName);
                // Only scan the dedicated upload directories, never paths supplied by database rows.
                foreach ($disk->allFiles('document-imports') as $path) {
                    if (basename($path) === '.gitignore') {
                        continue;
                    }
                    if (! $this->option('dry-run') && ! $disk->delete($path)) {
                        throw new RuntimeException('Originaldatei konnte nicht gelöscht werden.');
                    }
                    $count++;
                }
            }
            if (! $this->option('dry-run')) {
                DocumentImport::query()->update(['file_path' => null, 'raw_ai_result' => null]);
            }
        } catch (Throwable) {
            $this->error('Löschen unvollständig. Speicherzugriff und Datenbank prüfen und den Befehl erneut ausführen.');

            return self::FAILURE;
        }
        $this->info($this->option('dry-run') ? $count.' Originaldateien zum Löschen gefunden.' : $count.' Originaldateien gelöscht. Vorschläge und Termine bleiben erhalten.');

        return self::SUCCESS;
    }
}
