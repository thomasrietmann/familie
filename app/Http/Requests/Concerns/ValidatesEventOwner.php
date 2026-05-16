<?php

namespace App\Http\Requests\Concerns;

use App\Models\Child;
use App\Models\Family;
use App\Models\User;
use Illuminate\Validation\Validator;

trait ValidatesEventOwner
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $family = $this->route('family') ?? $this->route('event')?->family ?? $this->route('suggestion')?->family;
            $ownerType = $this->input('owner_type');
            $ownerId = $this->input('owner_id');

            if (! $family instanceof Family) {
                return;
            }

            if ($ownerType === 'family' && filled($ownerId)) {
                $validator->errors()->add('owner_id', 'Bei Zugehörigkeit zur ganzen Familie muss owner_id leer sein.');
            }

            if (in_array($ownerType, ['user', 'child'], true) && blank($ownerId)) {
                $validator->errors()->add('owner_id', 'Bitte wählen Sie die konkrete Person aus.');
                return;
            }

            if ($ownerType === 'user') {
                $hasAccess = User::whereKey($ownerId)
                    ->whereHas('families', function ($query) use ($family): void {
                        $query->where('families.id', $family->id)->where('family_users.status', 'active');
                    })->exists();

                if (! $hasAccess) {
                    $validator->errors()->add('owner_id', 'Dieses Elternteil hat keinen aktiven Zugriff auf die Familie.');
                }
            }

            if ($ownerType === 'child' && ! Child::where('family_id', $family->id)->whereKey($ownerId)->exists()) {
                $validator->errors()->add('owner_id', 'Dieses Kind gehört nicht zu dieser Familie.');
            }
        });
    }

    protected function eventRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'in:family_trip,playdate,birthday,school,childcare,medical,sport,holiday,meeting,other'],
            'visibility' => ['required', 'in:family,parents_only'],
            'owner_type' => ['required', 'in:family,user,child'],
            'owner_id' => ['nullable', 'integer'],
            'status' => ['required', 'in:planned,confirmed,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function normalizedEventData(): array
    {
        $data = $this->validated();
        $data['all_day'] = $this->boolean('all_day');
        $data['owner_type'] = $data['owner_type'] ?? 'family';
        $data['owner_id'] = $data['owner_id'] ?? null;

        if ($data['owner_type'] === 'family') {
            $data['owner_id'] = null;
        }

        return $data;
    }
}
