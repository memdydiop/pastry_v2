<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case ESPÈCES = 'Espèces';
    case WAVE = 'Wave';
    case ORANGE_MONEY = 'Orange Money';
    case MOOV_MONEY = 'Moov Money';
    case CHÈQUE = 'Chèque';
    case VIREMENT = 'Virement';

    public function label(): string
    {
        return match ($this) {
            self::ESPÈCES => 'Espèces',
            self::WAVE => 'Wave',
            self::ORANGE_MONEY => 'Orange Money',
            self::MOOV_MONEY => 'Moov Money',
            self::CHÈQUE => 'Chèque',
            self::VIREMENT => 'Virement bancaire',
        };
    }
}
