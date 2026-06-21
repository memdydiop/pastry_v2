<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Plan;
use App\Models\PlanFeatureValue;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    private array $plans = [
        [
            'name' => 'Standard',
            'slug' => 'standard',
            'description' => 'Pour les petites pâtisseries qui débutent',
            'price' => 0,
            'sort' => 1,
            'features' => [
                PlanFeature::STOCK_MANAGEMENT->value => true,
                PlanFeature::RECIPES->value => true,
                PlanFeature::SUPPLIERS->value => true,
                PlanFeature::ORDERS_ADVANCED->value => true,
                PlanFeature::INVOICING->value => false,
                PlanFeature::DELIVERY_PARTNERS->value => false,
                PlanFeature::WHATSAPP->value => false,
                PlanFeature::EXPERIENCES->value => false,
                PlanFeature::MULTI_USER->value => false,
                PlanFeature::REPORTS->value => false,
                PlanFeature::API_ACCESS->value => false,
                PlanFeature::EXPORT->value => false,
                'max_users' => 1,
                'max_orders_per_month' => 100,
                'max_ingredients' => 50,
                'max_recipes' => 30,
                'max_clients' => 50,
            ],
        ],
        [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Pour les pâtisseries en pleine croissance',
            'price' => 15000,
            'sort' => 2,
            'features' => [
                PlanFeature::STOCK_MANAGEMENT->value => true,
                PlanFeature::RECIPES->value => true,
                PlanFeature::SUPPLIERS->value => true,
                PlanFeature::ORDERS_ADVANCED->value => true,
                PlanFeature::INVOICING->value => true,
                PlanFeature::DELIVERY_PARTNERS->value => true,
                PlanFeature::WHATSAPP->value => true,
                PlanFeature::EXPERIENCES->value => false,
                PlanFeature::MULTI_USER->value => true,
                PlanFeature::REPORTS->value => true,
                PlanFeature::API_ACCESS->value => false,
                PlanFeature::EXPORT->value => true,
                'max_users' => 5,
                'max_orders_per_month' => 1000,
                'max_ingredients' => 200,
                'max_recipes' => 100,
                'max_clients' => 500,
            ],
        ],
        [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'Pour les chaînes et grandes maisons',
            'price' => 50000,
            'sort' => 3,
            'features' => [
                PlanFeature::STOCK_MANAGEMENT->value => true,
                PlanFeature::RECIPES->value => true,
                PlanFeature::SUPPLIERS->value => true,
                PlanFeature::ORDERS_ADVANCED->value => true,
                PlanFeature::INVOICING->value => true,
                PlanFeature::DELIVERY_PARTNERS->value => true,
                PlanFeature::WHATSAPP->value => true,
                PlanFeature::EXPERIENCES->value => true,
                PlanFeature::MULTI_USER->value => true,
                PlanFeature::REPORTS->value => true,
                PlanFeature::API_ACCESS->value => true,
                PlanFeature::EXPORT->value => true,
                'max_users' => 20,
                'max_orders_per_month' => 5000,
                'max_ingredients' => 1000,
                'max_recipes' => 500,
                'max_clients' => 2000,
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->plans as $data) {
            $features = $data['features'];
            unset($data['features']);

            $plan = Plan::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            foreach ($features as $feature => $value) {
                PlanFeatureValue::updateOrCreate(
                    ['plan_id' => $plan->id, 'feature' => $feature],
                    ['value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value]
                );
            }
        }
    }
}
