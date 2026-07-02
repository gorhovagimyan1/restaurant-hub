<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full order representation for the staff dashboard / live orders board.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'table' => [
                'id' => $this->restaurant_table_id,
                'name' => $this->whenLoaded('table', fn () => $this->table?->name),
                'bill_requested' => $this->whenLoaded('table', fn () => (bool) $this->table?->bill_requested_at),
            ],
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes' => $this->notes,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'service_charge' => (float) $this->service_charge,
            'total' => (float) $this->total,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'notes' => $item->notes,
                'status' => $item->status->value,
            ])),
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
