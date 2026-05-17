<?php

namespace App\Models;

use App\Support\MemberColorPalette;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Child extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'first_name',
        'last_name',
        'birthdate',
        'member_color',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FamilyEvent::class, 'owner_id')
            ->where('owner_type', 'child');
    }

    public function displayName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    public function memberColorHex(): string
    {
        return MemberColorPalette::hex($this->member_color);
    }
}
