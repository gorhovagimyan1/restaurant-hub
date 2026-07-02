<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PlaceOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\TableQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Place a guest order for a scanned table.
     *
     * The QR token identifies the table + restaurant, so no auth is needed.
     * Line prices/names are snapshotted from the current products and totals
     * are computed server-side from the restaurant's tax/service settings.
     */
    public function store(PlaceOrderRequest $request, TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant.settings')->firstOrFail();
        $restaurant = $table->restaurant;
        $settings = $restaurant->settings;

        abort_unless($restaurant->is_active, 404);
        abort_if(
            $settings && ! $settings->allow_guest_orders,
            422,
            'This restaurant is not accepting online orders right now.',
        );

        // Only this restaurant's available products can be ordered.
        $requested = collect($request->validated('items'));
        $products = Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('id', $requested->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $lines = $requested->map(function (array $item) use ($products) {
            $product = $products->get($item['product_id']);

            abort_if($product === null, 422, 'One of the selected items is unavailable.');
            abort_unless($product->is_available, 422, "\"{$product->name}\" is currently sold out.");

            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $product->price;

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => round($unitPrice * $quantity, 2),
                'notes' => $item['notes'] ?? null,
            ];
        });

        $subtotal = round((float) $lines->sum('total_price'), 2);
        $tax = round($subtotal * ((float) ($settings?->tax_percentage ?? 0)) / 100, 2);
        $serviceCharge = round($subtotal * ((float) ($settings?->service_charge ?? 0)) / 100, 2);
        $total = round($subtotal + $tax + $serviceCharge, 2);

        $order = DB::transaction(function () use (
            $request, $restaurant, $table, $lines, $subtotal, $tax, $serviceCharge, $total, $settings
        ) {
            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'restaurant_table_id' => $table->id,
                'customer_name' => $request->validated('customer_name'),
                'customer_phone' => $request->validated('customer_phone'),
                'notes' => $request->validated('notes'),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'service_charge' => $serviceCharge,
                'total' => $total,
                'status' => $settings?->auto_accept_orders
                    ? OrderStatus::Accepted
                    : OrderStatus::Pending,
                'ordered_at' => now(),
            ]);

            $order->items()->createMany($lines->all());

            // The table is now in use.
            if ($table->status !== TableStatus::Occupied) {
                $table->update(['status' => TableStatus::Occupied]);
            }

            return $order;
        });

        return ApiResponse::created([
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'total' => (float) $order->total,
        ], 'Your order has been sent to the kitchen.');
    }
}
