<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Automatically assigns a UUID to the model's `uuid` column on creation.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Use the UUID for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
