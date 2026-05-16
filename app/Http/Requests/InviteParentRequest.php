<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inviteParent', $this->route('family')) ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
