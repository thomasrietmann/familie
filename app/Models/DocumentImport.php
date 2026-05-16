<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'uploaded_by_user_id',
        'title',
        'file_path',
        'original_filename',
        'file_type',
        'status',
        'target_type',
        'target_id',
        'raw_ai_result',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'raw_ai_result' => 'array',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(ImportedEventSuggestion::class);
    }

    public function targetDisplayName(): string
    {
        return match ($this->target_type) {
            'family' => 'Ganze Familie',
            'user' => User::find($this->target_id)?->name ?? 'Elternteil',
            'child' => Child::find($this->target_id)?->displayName() ?? 'Kind',
            default => 'Ganze Familie',
        };
    }
}
