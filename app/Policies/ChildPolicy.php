<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\User;

class ChildPolicy
{
    public function view(User $user, Child $child): bool
    {
        return $user->can('view', $child->family);
    }

    public function update(User $user, Child $child): bool
    {
        return $user->can('update', $child->family);
    }

    public function delete(User $user, Child $child): bool
    {
        return $user->can('update', $child->family);
    }
}
