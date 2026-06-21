<?php

namespace App\Listeners;

use App\Enums\PlanFeature;
use App\Models\PlanFeatureValue;
use Exception;
use Illuminate\Console\Application as Artisan;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Events\TenantCreated;

class SetupNewTenant
{
    public function handle(TenantCreated $event): void
    {
        $tenant = $event->tenant;

        try {
            Artisan::call('tenants:migrate', [
                '--tenant' => $tenant->id,
                '--force' => true,
            ]);

            tenancy()->initialize($tenant);

            $this->seedRolesAndPermissions();
            $this->seedWhatsAppTemplates();

            tenancy()->end();

            Log::info('Tenant setup complete', ['tenant_id' => $tenant->id]);
        } catch (Exception $e) {
            Log::error('Tenant setup failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function seedRolesAndPermissions(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage-settings',
            'manage-orders',
            'manage-stock',
            'manage-finances',
        ];

        foreach ($permissions as $name) {
            \Spatie\Permission\Models\Permission::findOrCreate($name);
        }

        \Spatie\Permission\Models\Role::findOrCreate('ghost');
        $admin = \Spatie\Permission\Models\Role::findOrCreate('Gérant/Admin');
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        $comptable = \Spatie\Permission\Models\Role::findOrCreate('Comptable');
        $comptable->givePermissionTo(['manage-finances']);

        $chef = \Spatie\Permission\Models\Role::findOrCreate('Chef Pâtissier');
        $chef->givePermissionTo(['manage-orders', 'manage-stock']);

        $patissier = \Spatie\Permission\Models\Role::findOrCreate('Pâtissier');
        $patissier->givePermissionTo(['manage-orders']);

        $caissier = \Spatie\Permission\Models\Role::findOrCreate('Caissier');
        $caissier->givePermissionTo(['manage-orders', 'manage-finances']);
    }

    private function seedWhatsAppTemplates(): void
    {
        $templates = [
            ['key' => 'order_contact', 'label' => 'Contact client - Commande', 'content' => 'Bonjour {client_name}, nous avons bien reçu votre commande #{reference} d\'un montant de {total_amount} FCFA. Nous vous contacterons dès qu\'elle sera prête. Merci pour votre confiance !'],
            ['key' => 'order_in_progress', 'label' => 'Commande en cours', 'content' => 'Bonjour {client_name}, votre commande #{reference} est actuellement en cours de préparation. Nous vous tiendrons informé dès qu\'elle sera prête.'],
            ['key' => 'order_ready', 'label' => 'Commande prête', 'content' => 'Bonjour {client_name}, bonne nouvelle ! Votre commande #{reference} est prête. Vous pouvez passer la récupérer à notre boutique. Montant restant à payer : {remaining_amount} FCFA.'],
            ['key' => 'payment_reminder', 'label' => 'Rappel de paiement', 'content' => 'Bonjour {client_name}, nous vous rappelons qu\'il reste un solde de {remaining_amount} FCFA pour votre commande #{reference}. Merci de régulariser votre situation.'],
            ['key' => 'delivery_update', 'label' => 'Mise à jour livraison', 'content' => 'Bonjour {client_name}, votre commande #{reference} est en cours de livraison et devrait vous parvenir dans les {delivery_time} minutes.'],
        ];

        foreach ($templates as $template) {
            \App\Models\WhatsAppTemplate::firstOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}
