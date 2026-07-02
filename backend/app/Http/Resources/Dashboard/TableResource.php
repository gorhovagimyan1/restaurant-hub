<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A restaurant table with its QR token, for the tables/QR management screen.
 *
 * @mixin \App\Models\RestaurantTable
 */
class TableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'qr_token' => $this->qrCode?->token,
        ];
    }
}
