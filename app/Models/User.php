<?php

namespace App\Models;

use App\Support\MemberColorPalette;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'member_color',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class, 'family_users')
            ->withPivot(['role', 'status', 'invited_at', 'accepted_at'])
            ->withTimestamps();
    }

    public function activeFamilies(): BelongsToMany
    {
        return $this->families()->wherePivot('status', 'active');
    }

    public function managedFamily(): ?Family
    {
        return $this->activeFamilies()
            ->with(['children', 'activeParents', 'documentImports'])
            ->orderBy('families.created_at')
            ->first();
    }

    public function hasManagedFamily(): bool
    {
        return $this->activeFamilies()->exists();
    }

    public function memberColorHex(): string
    {
        return MemberColorPalette::hex($this->member_color);
    }
}
