<?php

namespace App\Policies;

use App\Models\DocumentImport;
use App\Models\User;

class DocumentImportPolicy
{
    public function view(User $user, DocumentImport $documentImport): bool
    {
        return $user->can('view', $documentImport->family);
    }

    public function update(User $user, DocumentImport $documentImport): bool
    {
        return $user->can('update', $documentImport->family);
    }

    public function delete(User $user, DocumentImport $documentImport): bool
    {
        return $user->can('update', $documentImport->family);
    }
}
