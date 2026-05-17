<?php

namespace App\Models;

use App\Support\MemberColorPalette;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FamilyEvent extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'school',
        'holiday',
        'birthday',
        'excursion',
        'parent_evening',
        'doctor',
        'sports',
        'deadline',
        'other',
    ];

    public const CATEGORY_LABELS = [
        'school' => 'Schule',
        'holiday' => 'Ferien / Feiertag',
        'birthday' => 'Geburtstag',
        'excursion' => 'Ausflug',
        'parent_evening' => 'Elternabend',
        'doctor' => 'Arzt / Gesundheit',
        'sports' => 'Sport',
        'deadline' => 'Frist',
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

    public function ownerColorHex(): string
    {
        if ($this->isForUser()) {
            return User::find($this->owner_id)?->memberColorHex() ?? MemberColorPalette::hex(null);
        }

        if ($this->isForChild()) {
            return Child::find($this->owner_id)?->memberColorHex() ?? MemberColorPalette::hex(null);
        }

        return MemberColorPalette::hex(null);
    }

    public function dashboardDateLabel(): string
    {
        return $this->all_day
            ? $this->starts_at->format('d.m.Y').' · Ganztägig'
            : $this->starts_at->format('d.m.Y H:i');
    }

    public function dashboardTimeLabel(): string
    {
        if ($this->all_day) {
            return 'Ganztägig';
        }

        if ($this->ends_at) {
            return $this->starts_at->format('H:i').' - '.$this->ends_at->format('H:i');
        }

        return $this->starts_at->format('H:i');
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
