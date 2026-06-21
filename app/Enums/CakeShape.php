<?php

namespace App\Enums;

enum CakeShape: string
{
    case ROND = 'Rond';
    case CARRÉ = 'Carré';
    case RECTANGLE = 'Rectangle';
    case CŒUR = 'Cœur';
    case CHIFFRE = 'Chiffre';
    case PERSONNALISÉ = 'Personnalisé';

    public function label(): string
    {
        return match ($this) {
            self::ROND => 'Rond',
            self::CARRÉ => 'Carré',
            self::RECTANGLE => 'Rectangle',
            self::CŒUR => 'Cœur',
            self::CHIFFRE => 'Chiffre',
            self::PERSONNALISÉ => 'Personnalisé',
        };
    }
}
