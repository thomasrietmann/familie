<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FamilyEvent extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'family_trip',
        'playdate',
        'birthday',
        'school',
        'childcare',
        'medical',
        'sport',
        'holiday',
        'meeting',
        'other',
    ];

    public const CATEGORY_LABELS = [
        'family_trip' => 'Familienausflug',
        'playdate' => 'Spieltermin',
        'birthday' => 'Geburtstag',
        'school' => 'Schule',
        'childcare' => 'Betreuung',
        'medical' => 'Arzt / Gesundheit',
        'sport' => 'Sport',
        'holiday' => 'Ferien / Feiertag',
        'meeting' => 'Besprechung',
        'other' => 'Sonstiges',
    ];

    public const STATUS_LABELS = [
        'planned' => 'Geplant',
        'confirmed' => 'Bestätigt',
        'cancelled' => 'Abgesagt',
    ];

    public const VISIBILITY_LABELS = [
        'family' => 'Familie',
        'parents_only' => 'Nur Eltern',
    ];

    protected $fillable = [
        'family_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'all_day',
        'location',
        'category',
        'visibility',
        'owner_type',
        'owner_id',
        'status',
        'source',
        'document_import_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function documentImport(): BelongsTo
    {
        return $this->belongsTo(DocumentImport::class);
    }

    public function ownerDisplayName(): string
    {
        if ($this->isForFamily()) {
            return 'Ganze Familie';
        }

        if ($this->isForUser()) {
            return User::find($this->owner_id)?->name ?? 'Elternteil';
        }

        if ($this->isForChild()) {
            return Child::find($this->owner_id)?->displayName() ?? 'Kind';
        }

        return 'Unbekannt';
    }

    public function isForFamily(): bool
    {
        return $this->owner_type === 'family';
    }

    public function isForUser(): bool
    {
        return $this->owner_type === 'user';
    }

    public function isForChild(): bool
    {
        return $this->owner_type === 'child';
    }

    public function isToday(): bool
    {
        return $this->starts_at->isToday();
    }

    public function isTomorrow(): bool
    {
        return $this->starts_at->isTomorrow();
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at->greaterThanOrEqualTo(Carbon::today());
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'confirmed' => 'bg-emerald-500',
            'cancelled' => 'bg-rose-500',
            default => 'bg-amber-400',
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function visibilityLabel(): string
    {
        return self::VISIBILITY_LABELS[$this->visibility] ?? $this->visibility;
    }
}
