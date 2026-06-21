<?php

namespace App\Enums;

enum PlanFeature: string
{
    case STOCK_MANAGEMENT = 'stock_management';
    case INVOICING = 'invoicing';
    case RECIPES = 'recipes';
    case SUPPLIERS = 'suppliers';
    case DELIVERY_PARTNERS = 'delivery_partners';
    case ORDERS_ADVANCED = 'orders_advanced';
    case WHATSAPP = 'whatsapp';
    case EXPERIENCES = 'experiences';
    case MULTI_USER = 'multi_user';
    case REPORTS = 'reports';
    case API_ACCESS = 'api_access';
    case EXPORT = 'export';

    public function label(): string
    {
        return match ($this) {
            self::STOCK_MANAGEMENT => 'Gestion de stock',
            self::INVOICING => 'Facturation',
            self::RECIPES => 'Recettes',
            self::SUPPLIERS => 'Fournisseurs',
            self::DELIVERY_PARTNERS => 'Livraison',
            self::ORDERS_ADVANCED => 'Commandes avancées',
            self::WHATSAPP => 'WhatsApp',
            self::EXPERIENCES => 'Expériences',
            self::MULTI_USER => 'Utilisateurs multiples',
            self::REPORTS => 'Rapports',
            self::API_ACCESS => 'Accès API',
            self::EXPORT => 'Export de données',
        };
    }
}
