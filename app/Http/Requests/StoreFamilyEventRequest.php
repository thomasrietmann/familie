<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEventOwner;
use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyEventRequest extends FormRequest
{
    use ValidatesEventOwner;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('family')) ?? false;
    }

    public function rules(): array
    {
        return $this->eventRules();
    }

    public function data(): array
    {
        return $this->normalizedEventData();
    }
}
