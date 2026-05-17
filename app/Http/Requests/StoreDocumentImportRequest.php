<?php

namespace App\Http\Requests;

use App\Models\Child;
use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDocumentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('family')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf,docx,jpg,jpeg,png,webp,tif,tiff', 'max:10240'],
            'target_type' => ['required', 'in:family,user,child'],
            'target_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $family = $this->route('family');
            $targetType = $this->input('target_type');
            $targetId = $this->input('target_id');

            if (! $family instanceof Family) {
                return;
            }

            if ($targetType === 'family') {
                return;
            }

            if (blank($targetId)) {
                $validator->errors()->add('target_id', 'Bitte wählen Sie eine Person aus.');
                return;
            }

            if ($targetType === 'user') {
                $hasAccess = User::whereKey($targetId)
                    ->whereHas('families', function ($query) use ($family): void {
                        $query->where('families.id', $family->id)->where('family_users.status', 'active');
                    })->exists();

                if (! $hasAccess) {
                    $validator->errors()->add('target_id', 'Dieses Elternteil hat keinen aktiven Zugriff auf die Familie.');
                }
            }

            if ($targetType === 'child' && ! Child::where('family_id', $family->id)->whereKey($targetId)->exists()) {
                $validator->errors()->add('target_id', 'Dieses Kind gehört nicht zu dieser Familie.');
            }
        });
    }

    public function targetData(): array
    {
        $targetType = $this->validated('target_type');

        return [
            'target_type' => $targetType,
            'target_id' => $targetType === 'family' ? null : $this->validated('target_id'),
        ];
    }
}
