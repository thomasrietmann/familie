<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedEventSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_import_id',
        'family_id',
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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'confidence' => 'float',
        ];
    }

    public function documentImport(): BelongsTo
    {
        return $this->belongsTo(DocumentImport::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function ownerDisplayName(): string
    {
        return match ($this->suggested_owner_type) {
            'family' => 'Ganze Familie',
            'user' => User::find($this->suggested_owner_id)?->name ?? 'Elternteil',
            'child' => Child::find($this->suggested_owner_id)?->displayName() ?? 'Kind',
            default => 'Noch nicht zugeordnet',
        };
    }
}
