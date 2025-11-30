<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePollingStationRequest extends FormRequest
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
            'election_id' => ['nullable', 'integer', 'exists:elections,id'],
            'town_id' => ['required', 'integer', 'exists:towns,id'],
            'station_number' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:1000'],
            'registered_voters' => ['nullable', 'integer'],
            'white_papers_count' => ['nullable', 'integer'],
            'cancelled_papers_count' => ['nullable', 'integer'],
            'voters_count' => ['nullable', 'integer'],
            'is_open' => ['nullable', 'boolean'],
            'is_on_hold' => ['nullable', 'boolean'],
            'is_closed' => ['nullable', 'boolean'],
            'is_done' => ['nullable', 'boolean'],
            'is_checked' => ['nullable', 'boolean'],
            'is_final' => ['nullable', 'boolean'],
        ];
    }
}
