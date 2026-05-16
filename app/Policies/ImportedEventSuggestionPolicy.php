<?php

namespace App\Policies;

use App\Models\ImportedEventSuggestion;
use App\Models\User;

class ImportedEventSuggestionPolicy
{
    public function view(User $user, ImportedEventSuggestion $importedEventSuggestion): bool
    {
        return $user->can('view', $importedEventSuggestion->family);
    }

    public function update(User $user, ImportedEventSuggestion $importedEventSuggestion): bool
    {
        return $user->can('update', $importedEventSuggestion->family);
    }
}
