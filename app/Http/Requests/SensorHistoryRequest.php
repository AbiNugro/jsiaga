<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SensorHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['range' => $this->input('range', '1h')]);
    }

    public function rules(): array
    {
        return [
            'range' => ['required', Rule::in(['1h', '6h', '24h', '7d'])],
        ];
    }
}
