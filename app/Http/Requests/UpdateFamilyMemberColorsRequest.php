<?php

namespace App\Http\Requests;

use App\Models\Child;
use App\Models\Family;
use App\Models\User;
use App\Support\MemberColorPalette;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFamilyMemberColorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('family')) ?? false;
    }

    public function rules(): array
    {
        return [
            'parent_colors' => ['nullable', 'array'],
            'parent_colors.*' => ['required', Rule::in(MemberColorPalette::keys())],
            'child_colors' => ['nullable', 'array'],
            'child_colors.*' => ['required', Rule::in(MemberColorPalette::keys())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $family = $this->route('family');

            if (! $family instanceof Family) {
                return;
            }

            foreach (array_keys($this->input('parent_colors', [])) as $parentId) {
                $hasAccess = User::whereKey($parentId)
                    ->whereHas('families', function ($query) use ($family): void {
                        $query->where('families.id', $family->id)->where('family_users.status', 'active');
                    })
                    ->exists();

                if (! $hasAccess) {
                    $validator->errors()->add('parent_colors', 'Ein Elternteil gehört nicht aktiv zu dieser Familie.');
                }
            }

            foreach (array_keys($this->input('child_colors', [])) as $childId) {
                if (! Child::where('family_id', $family->id)->whereKey($childId)->exists()) {
                    $validator->errors()->add('child_colors', 'Ein Kind gehört nicht zu dieser Familie.');
                }
            }
        });
    }
}
