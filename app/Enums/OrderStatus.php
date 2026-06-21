<?php

namespace App\Enums;

enum OrderStatus: string
{
    case EN_ATTENTE = 'En attente';
    case ACOMPTE_PERÇU = 'Acompte perçu';
    case CONFIRMÉE = 'Confirmée';
    case EN_PRODUCTION = 'En production';
    case PRÊTE = 'Prête';
    case EN_LIVRAISON = 'En cours de livraison';
    case LIVRÉE = 'Livrée';
    case ANNULÉE = 'Annulée';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::ACOMPTE_PERÇU => 'Acompte perçu',
            self::CONFIRMÉE => 'Confirmée',
            self::EN_PRODUCTION => 'En production',
            self::PRÊTE => 'Prête',
            self::EN_LIVRAISON => 'En cours de livraison',
            self::LIVRÉE => 'Livrée',
            self::ANNULÉE => 'Annulée',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'neutral',
            self::ACOMPTE_PERÇU => 'warning',
            self::CONFIRMÉE => 'indigo',
            self::EN_PRODUCTION => 'pivoting',
            self::PRÊTE => 'blue',
            self::EN_LIVRAISON => 'pink',
            self::LIVRÉE => 'success',
            self::ANNULÉE => 'danger',
        };
    }
}
