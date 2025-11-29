<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBallotEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user is assigned to this station
        $station = $this->route('station');
        $stationId = $station instanceof \App\Models\PollingStation ? $station->id : $station;

        return $this->user()->stationAssignments()
            ->where('polling_station_id', $stationId)
            ->where('role', 'counter')
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return array<string, string|array<string>>
     */
    public function rules(): array
    {
        return [
            'ballot_type' => ['required', Rule::in(['valid_list', 'valid_preferential', 'white', 'cancelled'])],
            'list_id' => ['required_if:ballot_type,valid_list,valid_preferential', 'nullable', 'exists:electoral_lists,id'],
            'candidate_id' => ['required_if:ballot_type,valid_preferential', 'nullable', 'exists:candidates,id'],
            'cancellation_reason' => ['required_if:ballot_type,cancelled', 'nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ballot_type.required' => 'نوع الورقة مطلوب',
            'ballot_type.in' => 'نوع الورقة غير صالح',
            'list_id.required_if' => 'اللائحة مطلوبة للأصوات الصحيحة',
            'list_id.exists' => 'اللائحة المختارة غير موجودة',
            'candidate_id.required_if' => 'المرشح مطلوب للأصوات التفضيلية',
            'candidate_id.exists' => 'المرشح المختار غير موجود',
            'cancellation_reason.required_if' => 'سبب الإلغاء مطلوب للأوراق الملغاة',
        ];
    }
}
