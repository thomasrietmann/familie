<?php

namespace App\Policies;

use App\Models\Family;
use App\Models\User;

class FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Family $family): bool
    {
        return $family->users()
            ->whereKey($user->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    public function create(User $user): bool
    {
        return ! $user->hasManagedFamily();
    }

    public function update(User $user, Family $family): bool
    {
        return $this->view($user, $family);
    }

    public function delete(User $user, Family $family): bool
    {
        return $family->owner_user_id === $user->id;
    }

    public function inviteParent(User $user, Family $family): bool
    {
        return $family->owner_user_id === $user->id;
    }
}
