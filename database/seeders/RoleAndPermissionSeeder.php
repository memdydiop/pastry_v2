<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Nettoyer le cache des permissions de Spatie pour éviter les conflits en mémoire
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Définition et création sécurisée de toutes les permissions système
        $permissions = [
            'manage-settings', // Accès à la configuration globale de la pâtisserie
            'manage-orders',   // Gestion des commandes clients et encaissements
            'manage-stock',    // Gestion des stocks d'ingrédients et matières premières
            'manage-finances', // Accès à la comptabilité, factures et rapports de vente
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName);
        }

        // 3. Initialisation du Rôle de Sécurité Masqué (Ghost)
        $ghostRole = Role::findOrCreate('ghost');

        // 4. Initialisation du Rôle Direction (Gérant/Admin)
        $adminRole = Role::findOrCreate('Gérant/Admin');
        $adminRole->givePermissionTo(Permission::all()); // Le gérant pilote tous les modules

        // 5. Initialisation des Rôles Métiers (Correction chirurgicale des noms et findOrCreate)

        // Comptable : Uniquement axé sur le module 5 (Finances)
        $comptableRole = Role::findOrCreate('Comptable');
        $comptableRole->givePermissionTo(['manage-finances']);

        // Chef Pâtissier : SYNCHRONISÉ avec web.php (Chef Pâtissier) pour débloquer les stocks et commandes
        $chefRole = Role::findOrCreate('Chef Pâtissier');
        $chefRole->givePermissionTo(['manage-orders', 'manage-stock']);

        // Pâtissier standard : Uniquement habilité à voir/traiter les fiches de commandes assignées
        $patissierRole = Role::findOrCreate('Pâtissier');
        $patissierRole->givePermissionTo(['manage-orders']);

        // Caissier/Vendeur : Gestion des commandes en boutique et enregistrement des flux financiers directs
        $caissierRole = Role::findOrCreate('Caissier');
        $caissierRole->givePermissionTo(['manage-orders', 'manage-finances']);
    }
}
