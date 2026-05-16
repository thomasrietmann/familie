<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEventOwner;
use Illuminate\Foundation\Http\FormRequest;

class UpdateImportedEventSuggestionRequest extends FormRequest
{
    use ValidatesEventOwner;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('suggestion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'in:family_trip,playdate,birthday,school,childcare,medical,sport,holiday,meeting,other'],
            'owner_type' => ['required', 'in:family,user,child'],
            'owner_id' => ['nullable', 'integer'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
        ];
    }

    public function suggestionData(): array
    {
        $data = $this->validated();
        $data['all_day'] = $this->boolean('all_day');
        $data['suggested_owner_type'] = $data['owner_type'];
        $data['suggested_owner_id'] = $data['owner_type'] === 'family' ? null : $data['owner_id'];
        unset($data['owner_type'], $data['owner_id']);

        return $data;
    }
}
