<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');

            // Référence unique de la commande (ex: CMD-2026-0001)
            $table->string('reference')->unique();

            // Informations Client (Directes pour simplifier au début)
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('contact_phone_2', 20)->nullable();
            $table->string('contact_phone_3', 20)->nullable();

            // Spécifications de la Pâtisserie (Très important pour le sur-mesure)
            $table->string('cake_type')->nullable(); // Ex: Génoise, Forêt Noire, Pièce montée
            $table->integer('tiers_count')->default(1); // Nombre d'étages
            $table->integer('servings_count')->nullable(); // Nombre de parts (estimé)
            $table->text('flavors_details')->nullable(); // Parfums (Chocolat, Vanille, Fruits...)
            $table->text('decorations_details')->nullable(); // Pochoir, inscriptions, thèmes
            $table->text('theme_description')->nullable();
            $table->text('colors_requested')->nullable();
            $table->text('inscription_text')->nullable();

            // Logistique de l'atelier
            $table->dateTime('delivery_due_at'); // Date et heure de retrait/livraison
            $table->text('delivery_address')->nullable();
            $table->text('conservation_notes')->nullable();
            $table->text('allergens')->nullable();
            $table->text('notes')->nullable(); // Consignes spéciales de livraison

            // Suivi Financier
            $table->decimal('total_amount', 10, 2)->default(0.00); // Prix total
            $table->decimal('advance_payment', 10, 2)->default(0.00); // Acompte versé

            // États de la commande (En attente, En préparation, Décoration, Prêt, Livré, Annulé)
            $table->string('status')->default('En attente');

            // Annulation
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            // Traçabilité : Qui a enregistré la commande (Caissier ou Admin)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
