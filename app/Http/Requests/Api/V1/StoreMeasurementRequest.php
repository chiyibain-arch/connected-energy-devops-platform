<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Middleware\AuthenticateDevice;
use App\Models\Device;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementRequest extends FormRequest
{
    public function authenticatedDevice(): Device
    {
        return $this->attributes->get(AuthenticateDevice::REQUEST_ATTRIBUTE);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string'],
            'voltage' => ['required', 'numeric', 'min:0'],
            'current' => ['required', 'numeric', 'min:0'],
            'power' => ['required', 'numeric', 'min:0'],
            'timestamp' => ['required', 'date'],
        ];
    }
}
