<?php

namespace App\Services\Stock;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Notifications\LowStockNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Portion counts for products that opt in to stock tracking
 * (`products.stock_quantity` not null). Untracked products pass straight
 * through. The numbers are staff-only; guests only ever see "sold out".
 */
class StockService
{
    /**
     * Deduct ordered quantities. Call inside the order transaction: rows are
     * locked so two guests can't both take the last portion, and an abort
     * rolls the whole order back.
     *
     * @param  iterable<array{product_id: int, quantity: int}>  $lines
     * @return Collection<int, Product> products that just fell to their alert level
     */
    public function deduct(Restaurant $restaurant, iterable $lines): Collection
    {
        $wanted = collect($lines)
            ->groupBy('product_id')
            ->map(fn (Collection $group) => (int) $group->sum('quantity'));

        $alerts = collect();

        if ($wanted->isEmpty()) {
            return $alerts;
        }

        $products = Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('id', $wanted->keys())
            ->whereNotNull('stock_quantity')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($products as $product) {
            $quantity = $wanted[$product->id];
            $before = $product->stock_quantity;

            // Deliberately vague: the exact count is not for guests.
            abort_if(
                $before < $quantity,
                422,
                __('Sorry, we don\'t have enough ":name" right now.', ['name' => $product->translated('name')]),
            );

            $product->update(['stock_quantity' => $before - $quantity]);

            if ($before > $product->lowStockLevel() && $product->stock_quantity <= $product->lowStockLevel()) {
                $alerts->push($product);
            }
        }

        return $alerts;
    }

    /**
     * The restaurant's current business day (local date, rolling over at
     * config('stock.reset_hour') rather than midnight).
     */
    public function businessDate(Restaurant $restaurant): string
    {
        return Carbon::now($restaurant->timezone ?: config('app.timezone'))
            ->subHours((int) config('stock.reset_hour'))
            ->toDateString();
    }

    /**
     * Refill every product with a daily_stock whose last reset wasn't today's
     * business day. Idempotent: running it again the same day changes nothing.
     *
     * @return int number of products refilled
     */
    public function resetDaily(): int
    {
        $count = 0;

        Restaurant::query()
            ->whereHas('products', fn ($q) => $q->whereNotNull('daily_stock'))
            ->each(function (Restaurant $restaurant) use (&$count) {
                $today = $this->businessDate($restaurant);

                $count += $restaurant->products()
                    ->whereNotNull('daily_stock')
                    ->where(fn ($q) => $q->whereNull('stock_reset_on')->orWhere('stock_reset_on', '<', $today))
                    ->update([
                        'stock_quantity' => DB::raw('daily_stock'),
                        'stock_reset_on' => $today,
                    ]);
            });

        return $count;
    }

    /**
     * Tracked products at or below their alert level (including out of stock),
     * for the in-app banner.
     *
     * @return Collection<int, Product>
     */
    public function lowItems(Restaurant $restaurant): Collection
    {
        return $restaurant->products()
            ->whereNotNull('stock_quantity')
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $p) => in_array($p->stockStatus(), ['low', 'out'], true))
            ->values();
    }

    /**
     * Put cancelled portions back on the shelf.
     *
     * @param  iterable<OrderItem>  $items
     */
    public function restore(iterable $items): void
    {
        foreach (collect($items)->groupBy('product_id') as $productId => $group) {
            if ($productId === null || $productId === '') {
                continue;
            }

            Product::query()
                ->whereKey($productId)
                ->whereNotNull('stock_quantity')
                ->increment('stock_quantity', (int) $group->sum('quantity'));
        }
    }

    /**
     * Email everyone who manages products at the restaurant.
     *
     * @param  Collection<int, Product>  $products
     */
    public function notifyLow(Restaurant $restaurant, Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $recipients = $restaurant->users()
            ->wherePivot('is_active', true)
            ->where('users.is_active', true)
            ->get()
            ->filter(fn ($user) => $user->can('products.manage'));

        if ($recipients->isEmpty()) {
            return;
        }

        // Queued, so it can't see the request's language: staff get the
        // restaurant's own default language instead.
        $locale = $restaurant->settings?->default_language;
        $locale = in_array($locale, \App\Http\Middleware\SetLocale::SUPPORTED, true) ? $locale : 'en';

        Notification::locale($locale)
            ->send($recipients, new LowStockNotification(
            $restaurant->name,
            $products->map(fn (Product $p) => [
                'name' => $p->translated('name', $locale),
                'quantity' => (int) $p->stock_quantity,
            ])->values()->all(),
        ));
    }
}
