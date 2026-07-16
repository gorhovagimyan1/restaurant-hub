<?php

namespace App\Http\Resources\Dashboard;

use App\Models\RestaurantSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The operational settings for the current restaurant.
 *
 * @mixin RestaurantSettings
 */
class RestaurantSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'default_language' => $this->default_language,
            'tax_percentage' => (float) $this->tax_percentage,
            'service_charge' => (float) $this->service_charge,
            'allow_guest_orders' => $this->allow_guest_orders,
            'require_table_selection' => $this->require_table_selection,
            'enable_waiter_call' => $this->enable_waiter_call,
            'enable_bill_request' => $this->enable_bill_request,
            'auto_accept_orders' => $this->auto_accept_orders,
        ];
    }
}
