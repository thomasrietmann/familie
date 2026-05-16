<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEventOwner;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyEventRequest extends FormRequest
{
    use ValidatesEventOwner;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return $this->eventRules();
    }

    public function eventData(): array
    {
        return $this->normalizedEventData();
    }
}
