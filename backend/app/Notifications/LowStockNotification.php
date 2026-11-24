<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells owners/managers that tracked products are running low or out, so
 * someone checks the stock and tops it up.
 */
class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{name: string, quantity: int}>  $items
     */
    public function __construct(
        private readonly string $restaurantName,
        private readonly array $items,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Low stock — :restaurant', ['restaurant' => $this->restaurantName]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('These items are running low and may need restocking:'));

        foreach ($this->items as $item) {
            $mail->line($item['quantity'] === 0
                ? '• '.__(':name — OUT OF STOCK', ['name' => $item['name']])
                : '• '.__(':name — :count left', ['name' => $item['name'], 'count' => $item['quantity']]));
        }

        return $mail
            ->action(__('Update stock'), rtrim((string) config('app.frontend_url'), '/').'/dashboard/menu')
            ->line(__('Guests see sold-out items as unavailable until stock is added.'));
    }
}
