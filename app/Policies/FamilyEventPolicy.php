<?php

namespace App\Policies;

use App\Models\FamilyEvent;
use App\Models\User;

class FamilyEventPolicy
{
    public function view(User $user, FamilyEvent $familyEvent): bool
    {
        return $user->can('view', $familyEvent->family);
    }

    public function update(User $user, FamilyEvent $familyEvent): bool
    {
        return $user->can('update', $familyEvent->family);
    }

    public function delete(User $user, FamilyEvent $familyEvent): bool
    {
        return $user->can('update', $familyEvent->family);
    }
}
