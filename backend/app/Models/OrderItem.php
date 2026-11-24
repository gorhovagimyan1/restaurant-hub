<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use BelongsToRestaurant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'total_price',
        'notes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'status' => \App\Enums\OrderItemStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The dish name in the viewer's language (guest or staff): the menu's
     * current wording while the dish exists, else the name captured when it
     * was ordered. Load `product` first to avoid a query per line.
     */
    public function localizedName(): string
    {
        return $this->product?->translated('name') ?? $this->product_name;
    }

    /**
     * The guest's rating of this line, if they left one.
     *
     * @return HasOne<ProductReview, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }

    /**
     * Derive restaurant_id from the parent order.
     */
    protected function restaurantParent(): ?Model
    {
        return $this->order;
    }
}
