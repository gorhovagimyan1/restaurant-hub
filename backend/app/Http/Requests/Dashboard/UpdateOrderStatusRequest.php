<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a staff status change on an order.
 */
class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(OrderStatus::values())],
            // Sent by the Kitchen / Bar boards: the shortcut then only moves
            // that board's items (the kitchen's "all ready" must not mark the
            // bar's cocktails ready).
            'board' => ['nullable', 'string', Rule::in(['kitchen', 'bar'])],
        ];
    }
}
