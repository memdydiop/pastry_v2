<?php

namespace App\Notifications;

use App\Mail\StockAlertMail;
use App\Models\Ingredient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StockAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ingredient $ingredient,
        public float $currentStock,
        public string $triggeredBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): StockAlertMail
    {
        return (new StockAlertMail($this->ingredient, $this->currentStock, $this->triggeredBy))
            ->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ingredient_id' => $this->ingredient->id,
            'ingredient_name' => $this->ingredient->name,
            'current_stock' => $this->currentStock,
            'alert_threshold' => $this->ingredient->alert_threshold,
            'unit' => $this->ingredient->unit->value,
            'triggered_by' => $this->triggeredBy,
            'is_critical' => $this->ingredient->is_critical,
        ];
    }
}
