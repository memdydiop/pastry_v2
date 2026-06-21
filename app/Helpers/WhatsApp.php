<?php

namespace App\Helpers;

use App\Models\Order;
use App\Models\WhatsAppTemplate;

class WhatsApp
{
    public static function link(string $phone, string $templateKey, array $replace = []): ?string
    {
        return WhatsAppTemplate::generateLink($phone, $templateKey, $replace);
    }

    public static function linkForOrder(Order $order, string $templateKey = 'order_contact'): ?string
    {
        $phones = array_filter([
            $order->client->phone,
            $order->contact_phone_2,
            $order->contact_phone_3,
        ]);

        foreach ($phones as $phone) {
            $link = static::link(
                phone: $phone,
                templateKey: $templateKey,
                replace: [
                    'client_name' => $order->client->name,
                    'reference' => $order->reference,
                    'total_amount' => number_format($order->total_amount, 0, ',', ' '),
                ],
            );

            if ($link) {
                return $link;
            }
        }

        return null;
    }
}
