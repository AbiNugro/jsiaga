<?php

namespace App\Http\Requests;

use App\Services\FloodStatusService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSensorReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distance' => ['required', 'numeric', 'between:0,'.FloodStatusService::MAX_SENSOR_DISTANCE_CM],
            'temperature' => ['nullable', 'numeric', 'between:-50,100'],
            'humidity' => ['nullable', 'numeric', 'between:0,100'],
            'light' => ['nullable', 'integer', 'between:0,1000000'],
            'recorded_at' => ['nullable', 'date', 'after_or_equal:'.now()->subDays(30)->toIso8601String(), 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
        ];
    }

    public function messages(): array
    {
        return [
            'distance.required' => 'Jarak air wajib diisi.',
            'distance.numeric' => 'Jarak air harus berupa angka.',
            'distance.between' => 'Jarak air harus berada di antara 0 dan '.FloodStatusService::MAX_SENSOR_DISTANCE_CM.' cm. Nilai di luar jangkauan sensor dianggap tidak valid.',
            'humidity.between' => 'Kelembapan harus berada di antara 0 dan 100.',
            'recorded_at.after_or_equal' => 'Waktu pencatatan terlalu lama.',
            'recorded_at.before_or_equal' => 'Waktu pencatatan tidak boleh jauh di masa depan.',
        ];
    }
}
