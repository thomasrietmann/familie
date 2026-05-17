<?php

namespace App\Http\Requests;

use App\Support\MemberColorPalette;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('child')) ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'member_color' => ['nullable', Rule::in(MemberColorPalette::keys())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
