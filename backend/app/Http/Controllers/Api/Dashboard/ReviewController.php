<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ResolvesRestaurant;

    /**
     * Guest reviews, newest first. Filter by `product_id` or `rating`.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        $reviews = ProductReview::query()
            ->where('restaurant_id', $restaurant->id)
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->integer('rating')))
            ->with(['product:id,name,translations', 'orderItem.order.table:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return ApiResponse::success([
            'reviews' => $reviews->getCollection()->map(fn (ProductReview $r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'product_name' => $r->product?->translated('name'),
                'rating' => $r->rating,
                'comment' => $r->comment,
                'table' => $r->orderItem?->order?->table?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ], 'Reviews retrieved.');
    }

    /**
     * Remove an abusive or mistaken review; the dish's rating updates itself.
     */
    public function destroy(Request $request, ProductReview $review): JsonResponse
    {
        $this->guardTenant($this->currentRestaurant($request), $review);

        $review->delete();

        return ApiResponse::success(null, 'Review deleted.');
    }
}
