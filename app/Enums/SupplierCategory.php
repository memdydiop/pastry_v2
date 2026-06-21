<?php

namespace App\Enums;

enum SupplierCategory: string
{
    case FOURNISSEUR = 'fournisseur';
    case SUPERMARCHÉ = 'supermarché';
    case MARCHÉ = 'marché';
    case BOUTIQUE = 'boutique';
    case GROSSISTE = 'grossiste';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::FOURNISSEUR => 'Fournisseur spécialisé',
            self::SUPERMARCHÉ => 'Supermarché',
            self::MARCHÉ => 'Marché',
            self::BOUTIQUE => 'Boutique de quartier',
            self::GROSSISTE => 'Grossiste / Semi-grossiste',
            self::AUTRE => 'Autre',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::FOURNISSEUR => 'indigo',
            self::SUPERMARCHÉ => 'blue',
            self::MARCHÉ => 'emerald',
            self::BOUTIQUE => 'amber',
            self::GROSSISTE => 'purple',
            self::AUTRE => 'neutral',
        };
    }
}
