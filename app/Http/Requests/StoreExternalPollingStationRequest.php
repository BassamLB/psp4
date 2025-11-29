<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalPollingStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? true;
    }

    /**
     * @return array<string, string|array<string>>
     */
    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:191'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'mission' => ['nullable', 'string', 'max:255'],
            'center_name' => ['required', 'string', 'max:255'],
            'center_address' => ['nullable', 'string', 'max:1000'],
            'from_id_number' => ['nullable', 'integer'],
            'to_id_number' => ['nullable', 'integer'],
            'electoral_districts' => ['nullable', 'array'],
            'electoral_districts.*' => ['integer', 'exists:electoral_districts,id'],
        ];
    }
}
