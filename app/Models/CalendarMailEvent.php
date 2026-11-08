<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarMailEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['revision_at' => 'datetime', 'sequence' => 'integer', 'cancelled' => 'boolean', 'was_imported' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FamilyEvent::class, 'family_event_id');
    }
}
