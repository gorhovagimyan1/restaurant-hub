<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateOrderStatusRequest;
use App\Http\Resources\Dashboard\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ResolvesRestaurant;

    /**
     * Live orders for the current restaurant.
     *
     * By default returns every active (non-final) order plus anything
     * created today, so the board shows the current service at a glance.
     * This endpoint is polled by the dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        $orders = $restaurant->orders()
            ->with(['items', 'table'])
            ->where(function ($query) {
                $query->whereNotIn('status', [
                    OrderStatus::Completed->value,
                    OrderStatus::Cancelled->value,
                ])->orWhereDate('created_at', today());
            })
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(
            OrderResource::collection($orders),
            'Orders retrieved.',
        );
    }

    /**
     * The full order history for the restaurant, paginated and filterable by
     * status or a search term (order number, table name, customer name).
     */
    public function history(Request $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        $query = $restaurant->orders()
            ->with(['items', 'table'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('table', fn ($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        return ApiResponse::success([
            'orders' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ], 'Order history retrieved.');
    }

    /**
     * Advance a whole order at once — the "mark all ready / deliver all"
     * shortcut. The action cascades to every item so per-item state stays in
     * sync with the order.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $order);

        $status = OrderStatus::from($request->validated('status'));

        // The service only runs forwards, and a settled or cancelled order is
        // closed for good — reopening one would quietly distort the day's
        // takings, which are summed from completed orders.
        abort_unless(
            $order->status->canTransitionTo($status),
            422,
            "An order that is {$order->status->value} cannot be marked {$status->value}.",
        );

        // Re-applying the current status is a no-op, so a double-tap on the
        // board doesn't re-cascade or re-stamp anything.
        if ($status === $order->status) {
            return ApiResponse::success(
                new OrderResource($order->load(['items', 'table'])),
                'Order status unchanged.',
            );
        }

        $this->cascadeToItems($order, $status);

        $order->status = $status;

        if ($status === OrderStatus::Completed && $order->completed_at === null) {
            $order->completed_at = now();
        }

        $order->save();

        // Free the table once it has no more active orders.
        if ($status->isFinal()) {
            $this->releaseTableIfIdle($order);
        }

        return ApiResponse::success(
            new OrderResource($order->load(['items', 'table'])),
            'Order status updated.',
        );
    }

    /**
     * Move the order's items forward to match a whole-order action (so
     * "mark all ready" also marks each dish ready). Items already further
     * along are left as-is.
     */
    private function cascadeToItems(Order $order, OrderStatus $status): void
    {
        $P = OrderItemStatus::Pending;
        $C = OrderItemStatus::Preparing;
        $R = OrderItemStatus::Ready;
        $S = OrderItemStatus::Served;

        $map = [
            OrderStatus::Preparing->value => ['from' => [$P], 'to' => $C],
            OrderStatus::Ready->value => ['from' => [$P, $C], 'to' => $R],
            OrderStatus::Served->value => ['from' => [$P, $C, $R], 'to' => $S],
            OrderStatus::Cancelled->value => ['from' => [$P, $C, $R, $S], 'to' => OrderItemStatus::Cancelled],
        ];

        if (! isset($map[$status->value])) {
            return;
        }

        $config = $map[$status->value];

        $order->items()
            ->whereIn('status', array_map(fn (OrderItemStatus $s) => $s->value, $config['from']))
            ->update(['status' => $config['to']->value]);
    }

    /**
     * Mark the order's table available again if it has no active orders left.
     */
    private function releaseTableIfIdle(Order $order): void
    {
        $table = $order->table;

        if ($table === null) {
            return;
        }

        $hasActive = $table->restaurant->orders()
            ->where('restaurant_table_id', $table->id)
            ->whereNotIn('status', [
                OrderStatus::Completed->value,
                OrderStatus::Cancelled->value,
            ])
            ->exists();

        if (! $hasActive && $table->status === TableStatus::Occupied) {
            $table->update(['status' => TableStatus::Available]);
        }
    }
}
