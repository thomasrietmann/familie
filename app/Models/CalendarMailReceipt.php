<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarMailReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['retry_at' => 'datetime'];
    }

    public function resultLabel(): string
    {
        return match ($this->result_code) {
            'ambiguous_sender' => 'Absender und Antwortadresse sind nicht eindeutig zuordenbar',
            'imported' => 'Termin übernommen oder aktualisiert',
            'cancelled' => 'Absage übernommen',
            'unchanged' => 'Bereits verarbeitet oder ältere Version',
            'unsupported_recurrence' => 'Terminserie nicht unterstützt – bitte Einzeltermine senden',
            'invalid_calendar' => 'Kalenderdatei ungültig oder nicht unterstützt',
            'no_calendar' => 'Keine iCalendar-Einladung gefunden',
            'authentication_failed' => 'Absender konnte nicht authentifiziert werden',
            'too_large' => 'Nachricht überschreitet 2 MiB',
            'temporary_failure' => $this->status === 'failed' ? 'Import fehlgeschlagen – bitte erneut senden' : 'Vorübergehender Fehler – erneuter Versuch folgt',
            default => 'Nachricht nicht übernommen',
        };
    }
}
