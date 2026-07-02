<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\OrderItemStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateOrderItemStatusRequest;
use App\Http\Resources\Dashboard\OrderResource;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class OrderItemController extends Controller
{
    use ResolvesRestaurant;

    /**
     * Advance a single item (cook it, mark it ready, deliver it). The parent
     * order's overall status is recomputed from its items afterwards.
     */
    public function updateStatus(UpdateOrderItemStatusRequest $request, OrderItem $orderItem): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $orderItem);

        $orderItem->update([
            'status' => OrderItemStatus::from($request->validated('status')),
        ]);

        $order = $orderItem->order;
        $order->syncStatusFromItems();

        return ApiResponse::success(
            new OrderResource($order->load(['items', 'table'])),
            'Item updated.',
        );
    }
}
