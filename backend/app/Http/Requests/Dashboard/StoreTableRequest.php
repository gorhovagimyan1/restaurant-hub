<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creation of a new restaurant table (a QR code is generated for it).
 */
class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
