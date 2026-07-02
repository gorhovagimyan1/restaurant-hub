<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resolves a scanned QR token into the table + restaurant context the
 * customer ordering screen needs (menu is fetched separately by slug).
 *
 * @mixin \App\Models\TableQrCode
 */
class TableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $restaurant = $this->table->restaurant;
        $settings = $restaurant->settings;

        return [
            'token' => $this->token,
            'table' => [
                'id' => $this->table->id,
                'name' => $this->table->name,
                'capacity' => $this->table->capacity,
            ],
            'restaurant' => [
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'logo' => $restaurant->logo,
                'currency' => $restaurant->currency,
            ],
            'ordering' => [
                'allow_guest_orders' => (bool) ($settings?->allow_guest_orders ?? true),
                'enable_waiter_call' => (bool) ($settings?->enable_waiter_call ?? true),
                'enable_bill_request' => (bool) ($settings?->enable_bill_request ?? true),
                'tax_percentage' => (float) ($settings?->tax_percentage ?? 0),
                'service_charge' => (float) ($settings?->service_charge ?? 0),
            ],
        ];
    }
}
