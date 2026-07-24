<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates edits to the current restaurant's operational settings.
 */
class UpdateSettingsRequest extends FormRequest
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
            'default_language' => ['required', 'string', 'max:5'],
            'tax_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_charge' => ['required', 'numeric', 'min:0', 'max:100'],
            'allow_guest_orders' => ['required', 'boolean'],
            'require_table_selection' => ['required', 'boolean'],
            'enable_waiter_call' => ['required', 'boolean'],
            'enable_bill_request' => ['required', 'boolean'],
            'auto_accept_orders' => ['required', 'boolean'],
        ];
    }
}
