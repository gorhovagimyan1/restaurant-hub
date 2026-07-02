<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\OrderItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a staff status change on a single order item.
 */
class UpdateOrderItemStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(OrderItemStatus::values())],
        ];
    }
}
