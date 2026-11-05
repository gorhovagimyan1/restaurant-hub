<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\OrderItemStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateOrderItemStatusRequest;
use App\Http\Resources\Dashboard\OrderResource;
use App\Models\OrderItem;
use App\Services\Stock\StockService;
use Illuminate\Http\JsonResponse;

class OrderItemController extends Controller
{
    use ResolvesRestaurant;

    /**
     * Advance a single item (cook it, mark it ready, deliver it). The parent
     * order's overall status is recomputed from its items afterwards.
     */
    public function updateStatus(UpdateOrderItemStatusRequest $request, OrderItem $orderItem, StockService $stock): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $orderItem);

        $status = OrderItemStatus::from($request->validated('status'));

        // Items advance forwards only, and a cancelled line stays cancelled.
        abort_unless(
            $orderItem->status->canTransitionTo($status),
            422,
            "An item that is {$orderItem->status->value} cannot be marked {$status->value}.",
        );

        // A cancelled dish goes back on the shelf, unless it was already served.
        if ($status === OrderItemStatus::Cancelled
            && ! in_array($orderItem->status, [OrderItemStatus::Cancelled, OrderItemStatus::Served], true)) {
            $stock->restore([$orderItem]);
        }

        $orderItem->update(['status' => $status]);

        $order = $orderItem->order;
        $order->syncStatusFromItems();

        return ApiResponse::success(
            new OrderResource($order->load(['items', 'table'])),
            'Item updated.',
        );
    }
}
