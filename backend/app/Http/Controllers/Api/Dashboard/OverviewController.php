<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\OrderResource;
use App\Models\OrderItem;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    use ResolvesRestaurant;

    /**
     * A snapshot of the restaurant's day: today's takings, live order load,
     * table occupancy, outstanding service calls, and a couple of lists.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        [$start, $end] = $this->todayRange($restaurant);

        return ApiResponse::success([
            'currency' => $restaurant->currency,
            'today' => $this->today($restaurant, $start, $end),
            'live' => $this->live($restaurant),
            'tables' => $this->tables($restaurant),
            'service' => $this->service($restaurant),
            'recent_orders' => OrderResource::collection(
                $restaurant->orders()->with(['items', 'table'])->latest()->limit(5)->get(),
            ),
            'top_products' => $this->topProducts($restaurant, $start, $end),
        ], 'Overview retrieved.');
    }

    /**
     * Today's boundaries in the restaurant's timezone, as UTC instants for
     * comparison against the (UTC) stored timestamps.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function todayRange(Restaurant $restaurant): array
    {
        $dayStart = Carbon::now($restaurant->timezone ?: config('app.timezone'))->startOfDay();

        return [
            $dayStart->copy()->utc(),
            $dayStart->copy()->addDay()->utc(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function today(Restaurant $restaurant, Carbon $start, Carbon $end): array
    {
        $ordersToday = $restaurant->orders()->whereBetween('created_at', [$start, $end])->count();

        $completed = $restaurant->orders()
            ->where('status', OrderStatus::Completed->value)
            ->whereBetween('completed_at', [$start, $end]);

        $completedCount = (clone $completed)->count();
        $revenue = (float) (clone $completed)->sum('total');

        return [
            'orders' => $ordersToday,
            'completed' => $completedCount,
            'revenue' => $revenue,
            'avg_order' => $completedCount > 0 ? round($revenue / $completedCount, 2) : 0.0,
        ];
    }

    /**
     * Live (non-final) order load, broken down by status.
     *
     * @return array<string, int>
     */
    private function live(Restaurant $restaurant): array
    {
        $byStatus = $restaurant->orders()
            ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'active_orders' => (int) $byStatus->sum(),
            'pending' => (int) $byStatus->get(OrderStatus::Pending->value, 0),
            'preparing' => (int) $byStatus->get(OrderStatus::Preparing->value, 0),
            'ready' => (int) $byStatus->get(OrderStatus::Ready->value, 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function tables(Restaurant $restaurant): array
    {
        $byStatus = $restaurant->tables()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'occupied' => (int) $byStatus->get(TableStatus::Occupied->value, 0),
            'available' => (int) $byStatus->get(TableStatus::Available->value, 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function service(Restaurant $restaurant): array
    {
        return [
            'waiter_calls' => $restaurant->tables()->whereNotNull('waiter_called_at')->count(),
            'bill_requests' => $restaurant->tables()->whereNotNull('bill_requested_at')->count(),
        ];
    }

    /**
     * Today's best-selling items by quantity.
     *
     * @return array<int, array{name: string, quantity: int}>
     */
    private function topProducts(Restaurant $restaurant, Carbon $start, Carbon $end): array
    {
        return OrderItem::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('product_name, SUM(quantity) as quantity')
            ->groupBy('product_name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(fn (OrderItem $item) => [
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
            ])
            ->all();
    }
}
