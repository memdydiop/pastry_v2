<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $oldToNew = [
        'En attente de paiement' => 'En attente',
        'En préparation' => 'En production',
        'Décoration' => 'En production',
        'Prêt' => 'Prête',
        'Livré' => 'Livrée',
        'Annulé' => 'Annulée',
    ];

    public function up(): void
    {
        foreach ($this->oldToNew as $old => $new) {
            DB::table('orders')
                ->where('status', $old)
                ->update(['status' => $new]);

            DB::table('order_status_logs')
                ->where('from_status', $old)
                ->update(['from_status' => $new]);

            DB::table('order_status_logs')
                ->where('to_status', $old)
                ->update(['to_status' => $new]);
        }
    }

    public function down(): void
    {
        // Not reversible — old values are lost after conversion
    }
};
