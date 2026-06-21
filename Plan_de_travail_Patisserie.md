# PLAN DE TRAVAIL & DÉVELOPPEMENT (V2)
## Application de Gestion pour Pâtisserie sur Mesure (Back-Office)

---

**Durée estimée : 9 semaines** (vs 7 en V1 — plus réaliste avec tests, CI/CD, pertes stock et conformité)

---

## Phase 1 : Initialisation & Socle Technique (Semaine 1)

*L'objectif est de mettre en place un environnement propre, la sécurité de base et le pipeline CI/CD.*

* **Étape 1.1 : Initialisation**
  * Configuration du projet Laravel 13 + Livewire 4 + Flux UI 2 + PostgreSQL.
  * Configuration de Redis / Horizon pour les jobs asynchrones (PDF, notifications).
  * Mise en place de Laravel Sail (Docker) pour environnement de développement reproductible.
* **Étape 1.2 : Sécurité & Utilisateurs (Module 1)**
  * Installation et configuration de `spatie/laravel-permission`.
  * Création des migrations et seeders des rôles : `Gérant/Admin`, `Pâtissier/Chef`, `Comptable`.
  * Mise en place de l'authentification (Laravel Fortify) + 2FA optionnelle pour Comptable / Admin.
  * Configuration de l'interface `sidebar.blade.php` : Séparation stricte entre **Mon Profil Personnel** et **Configuration Système**.
* **Étape 1.3 : CI/CD & Qualité**
  * Setup GitHub Actions : exécution des tests, Pint (lint), PHPStan à chaque push.
  * Configuration de Laravel Pint (PSR-12) et PHPStan (niveau 5).
  * Création du squelette des tests (PHPUnit/Pest) avec premier test qui échoue (vérification que le pipeline fonctionne).

---

## Phase 2 : Modélisation & Cœur Métier — Commandes "Sur Mesure" (Semaines 2 - 3)

*C'est la phase la plus importante : remplacer vos fichiers Excel par une saisie fluide et réactive.*

* **Étape 2.1 : Structure de la Base de Données (PostgreSQL)**
  * Création des tables : `clients`, `orders`, `order_levels` (multi-niveaux), `order_images`, `order_status_logs`.
  * Contrainte : la table `order_status_logs` est en **insert-only** (append-only) pour garantir la traçabilité.
* **Étape 2.2 : Composants Livewire Volt (Module 2)**
  * Développement du formulaire dynamique de prise de commande : ajout/suppression/réordonnancement d'un étage en temps réel sans rechargement de page.
  * Gestion de l'upload des images/croquis d'inspiration (validation taille/type, stockage S3/local).
  * Intégration du bouton d'action rapide générant le lien direct `wa.me/` vers le numéro WhatsApp du client.
* **Étape 2.3 : Tests du Module Commandes**
  * Tests unitaires : validation des règles de création commande, cycle de vie, permissions par rôle.
  * Tests de feature : parcours complet (création client → ajout étages → upload image → validation commande).

---

## Phase 3 : Gestion de l'Atelier, Stocks & Fournisseurs (Semaines 4 - 5)

*Assurer le suivi de la production, éviter les ruptures de matières premières, et tracer les pertes.*

* **Étape 3.1 : Gestion des Stocks & Alertes (Module 3)**
  * Création du module d'inventaire des ingrédients (unités adaptées : kg, L, unité, boîte).
  * Table `inventory_movements` en **insert-only** (pas de UPDATE/DELETE) pour chaque entrée/sortie/ajustement.
  * Alertes visuelles et notifications (email + in-app) sur le seuil critique du **beurre doux** (configurable).
  * **Pertes & Gaspillage :** Enregistrement des pertes (casse, périmé, gaspillage) via un mouvement de type `loss` dans `inventory_movements`. Calcul du ratio coût théorique / coût réel par recette.
* **Étape 3.2 : Fiches Techniques (Recettes)**
  * Création des tables `recipes` et `recipe_ingredients` avec coefficient de perte par ingrédient.
  * Note : La déduction des stocks n'est pas automatique — elle se fait exclusivement via la saisie manuelle de la consommation journalière (voir module Stocks).
* **Étape 3.3 : Fournisseurs & Logistique (Module 4)**
  * Annuaire fournisseurs (coordonnées, historique d'achats, délais moyens).
  * Gestion des livreurs (frais par course, notation, historique incidents).
  * Générateur de liste de courses consolidée sur une période (export PDF).
* **Étape 3.4 : Planning de Production (Module 6)**
  * Vue Calendrier / Kanban triée par date de livraison pour organiser le travail quotidien.
  * Drag & drop pour réaffecter les tâches (Cuisson, Montage, Décoration).
* **Étape 3.5 : Tests des Modules Stocks & Production**
  * Tests unitaires : consommation manuelle, alertes seuil, mouvements d'inventaire.
  * Tests de feature : saisie consommation journalière → vérification stock.

---

## Phase 4 : Comptabilité, Flux Financiers & Éditions (Semaine 6)

*Sécuriser les encaissements et suivre la rentabilité de l'enseigne.*

* **Étape 4.1 : Suivi Financier & Transactions (Module 5)**
  * Table `transactions` en **insert-only** avec types : `payment`, `refund`, `fee`.
  * Gestion des acomptes (50% à la commande) et soldes, modes de règlement (Mobile Money, Espèces, Virement).
* **Étape 4.2 : Règle Stricte des Remboursements**
  * Interface de remboursement intégrée et tracée **exclusivement** au niveau de la liste des transactions financières (pas modifiable depuis la fiche commande). Un refund est lié à la transaction d'origine.
* **Étape 4.3 : Paiements en ligne (optionnel)**
  * Intégration d'une passerelle Mobile Money (MTN MoMo / Orange Money API) ou Stripe.
  * Génération de lien de paiement pour l'acompte, partageable sur WhatsApp.
* **Étape 4.4 : Facturation & Quick Share (Module 7)**
  * Génération des reçus et factures PDF (Laravel DomPDF / Browsershot).
  * Templates WhatsApp configurables pour envoyer facture / statut commande en un clic.
* **Étape 4.5 : Tests du Module Financier**
  * Tests unitaires : calcul des totaux, validation des remboursements, génération PDF.
  * Tests de feature : acompte → solde → remboursement → vérification transaction.
  * Tests de permission : seul le Comptable/Admin peut valider/modifier une transaction.

---

## Phase 5 : Analytics & Tableau de Bord (Semaine 7)

*Piloter l'activité avec des indicateurs clés.*

* **Étape 5.1 : Tableau de Bord Analytique (Module 8)**
  * Suivi du Chiffre d'Affaires (journalier, mensuel, annuel).
  * Calcul de la marge réelle par gâteau : Prix de vente - Coût réel (ingrédients + pertes) - Frais de livraison.
  * Palmarès des parfums, thèmes et périodes les plus populaires.
  * Export CSV des indicateurs.
* **Étape 5.2 : Tests du Module Analytics**
  * Tests unitaires : calcul des marges, agrégations, filtres de période.
  * Tests de feature : génération du tableau de bord avec jeux de données fixtures.

---

## Phase 6 : Conformité, Sauvegardes & Déploiement (Semaines 8 - 9)

*Finaliser, sécuriser et mettre en production.*

* **Étape 6.1 : Conformité RGPD & Sécurité**
  * Déclaration de traitement des données personnelles (nom, téléphone, adresse).
  * Implémentation de l'anonymisation des clients à la demande (suppression logique, conservation 3 ans pour obligations comptables).
  * Rate limiting sur les endpoints critiques (connexion, création commande).
  * Vérification HTTPS (Let's Encrypt).
* **Étape 6.2 : Sauvegardes & Procédure de Restauration**
  * Backup automatique quotidien de PostgreSQL (rétention 30 jours, chiffré).
  * Backup des fichiers uploadés (S3 / local).
  * Documentation et test de la procédure de restauration.
* **Étape 6.3 : Tests de Réception & Recette Finale**
  * Exécution complète de la suite de tests (CI doit être vert).
  * Vérification de la couverture de code (≥ 80% sur modules financiers, commandes, stocks).
  * Tests responsive sur smartphone + atelier (Flux UI 2).
  * Validation utilisateur finale (recette) sur l'environnement de staging.
* **Étape 6.4 : Mise en Production**
  * Déploiement zero-downtime via Laravel Envoyer / Forge.
  * Configuration des logs et monitoring (Laravel Pulse ou équivalent).
  * Go live.
