<?php

namespace Database\Seeders;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'order_contact',
                'label' => 'Contact client - Commande',
                'message' => 'Bonjour {client_name}, je vous contacte à propos de votre commande {reference}.',
            ],
            [
                'key' => 'order_in_progress',
                'label' => 'Commande en cours',
                'message' => 'Bonjour {client_name}, votre commande {reference} est en cours de préparation. Nous vous tiendrons informé dès qu\'elle sera prête.',
            ],
            [
                'key' => 'order_ready',
                'label' => 'Commande prête',
                'message' => 'Bonjour {client_name}, votre commande {reference} d\'un montant de {total_amount} FCFA est prête ! Vous pouvez venir la récupérer à la pâtisserie.',
            ],
            [
                'key' => 'payment_reminder',
                'label' => 'Rappel de paiement',
                'message' => 'Bonjour {client_name}, un acompte de {total_amount} FCFA est demandé pour valider votre commande {reference}. Merci de procéder au règlement.',
            ],
            [
                'key' => 'delivery_update',
                'label' => 'Mise à jour livraison',
                'message' => 'Bonjour {client_name}, votre commande {reference} est en livraison. Vous serez livré dans les prochaines heures.',
            ],
        ];

        foreach ($templates as $template) {
            WhatsAppTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template,
            );
        }
    }
}
