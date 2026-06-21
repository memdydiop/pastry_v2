# CAHIER DES CHARGES FONCTIONNEL (V3)
## Application de Gestion pour Pâtisserie sur Mesure (Back-Office)

---

## 1. Présentation du Projet
L'objectif de ce projet est de concevoir et développer une application web interne (Back-Office) permettant de centraliser, piloter et optimiser l'ensemble des activités d'une enseigne de pâtisserie spécialisée exclusivement dans le **sur mesure** (sans vente directe en boutique).

Actuellement, les commandes sont initiées sur WhatsApp et consignées manuellement dans des fichiers Excel. La future application devra industrialiser ce processus, sécuriser les données financières, optimiser la gestion des stocks de matières premières et offrir une traçabilité rigoureuse de la production à la livraison.

---

## 2. Architecture Technique Ciblée
| Couche | Technologie | Rôle |
|---|---|---|
| **Backend** | Laravel 13 (dernière version stable) | API implicite, logique métier, files d'attente |
| **Frontend réactif** | Livewire 4 & Volt | Composants sans rechargement de page |
| **UI / Design** | Flux UI 2 (version gratuite, Tailwind CSS) | Thème responsive mobile-first |
| **Base de données** | PostgreSQL 16+ | Robustesse ACID, données transactionnelles |
| **Queue / Jobs** | Redis + Laravel Horizon | Traitement asynchrone (PDF, notifications, déductions stocks) |
| **Stockage fichiers** | Laravel Filesystem (S3 compatible / local) | Uploads croquis clients, exports PDF |

---

## 3. Spécifications Fonctionnelles & Modules Applicatifs

### Module 1 : Sécurité, Utilisateurs et Permissions
Ce module assure la protection des données de l'entreprise en limitant les accès selon les responsabilités de chacun (via le package `spatie/laravel-permission`).
- **Gestion des Profils :** L'espace de gestion du profil personnel de l'utilisateur (modification du mot de passe, informations personnelles) doit être **explicitement séparé du menu général des paramètres (Settings)** pour une meilleure clarté ergonomique.
- **Authentification :** Connexion par email/mot de passe (hash bcrypt). Option : 2FA (TOTP) pour le rôle Comptable et Administrateur.
- **Rôles & Permissions types :**
  - **Administrateur / Gérant :** Accès total à l'application (Statistiques, configurations financières, gestion des utilisateurs).
  - **Pâtissier / Chef :** Accès limité au planning de production, fiches techniques, et état des stocks.
  - **Comptable :** Accès exclusif aux modules financiers, rapports de ventes, dépenses et validation des transactions.

### Module 2 : Gestion des Commandes "Sur Mesure"
Le cœur de l'application. Conçu pour retranscrire fidèlement et rapidement les détails négociés sur WhatsApp.
- **Fiche Client :** Nom, prénom, numéro WhatsApp (avec bouton d'action direct générant un lien `wa.me/`), adresse et instructions de livraison.
- **Configuration Avancée du Gâteau :**
  - **Gestion Multi-niveaux :** Possibilité de définir dynamiquement le **nombre de niveaux (étages)** de la pièce montée, avec réordonnancement (glisser-déposer).
  - **Personnalisation par Niveau :** Pour chaque étage individuel, spécification du parfum (biscuit, crème), de la garniture, des restrictions allergènes et des dimensions indicatives (diamètre, hauteur). Champs libres pour annotations.
  - **Design & Visuels :** Champ de description du thème esthétique, couleurs demandées, texte à inscrire et zone de téléversement (upload) pour les images ou croquis d'inspiration partagés par le client.
- **Logistique :** Date et heure de retrait/livraison, contraintes de conservation (ex: zone réfrigérée obligatoire).
- **Cycle de vie de la commande :** En attente de paiement ➔ Acompte perçu ➔ Confirmée ➔ En production ➔ Prête ➔ En cours de livraison ➔ Livrée ➔ Annulée (avec motif). Historisation de chaque transition (created_at, user_id).

### Module 3 : Gestion des Stocks et Recettes
Optimisation des matières premières pour éviter les ruptures en pleine préparation.
- **Inventaire Centralisé :** Suivi en temps réel des ingrédients par unité de mesure adaptée (kg pour la farine/sucre, litre pour la crème, unité pour les œufs, boîtes de conditionnement). Chaque mouvement de stock (entrée/sortie/ajustement) est horodaté et lié à un utilisateur pour traçabilité complète.
- **Gestion spécifique du Beurre Doux :** Suivi rigoureux des stocks de beurre doux (matière critique) avec déclenchement d'alertes visuelles et notifications (email + in-app) dès l'atteinte d'un seuil critique défini.
- **Pertes & Gaspillage :** Enregistrement des pertes en production (ex: 10% de crème non utilisée, chute, erreur de dosage) pour confronter le **coût théorique** au **coût réel** par recette. Affichage d'un ratio d'efficacité matières.
- **Fiches Techniques (Recettes) :** Liaison entre les recettes de base et les ingrédients du stock avec **coefficient de perte** configurable par ingrédient. Lors de la validation d'une commande, le système évalue automatiquement la quantité de matières premières nécessaires et réserve le stock (soft lock jusqu'à confirmation).

### Module 4 : Gestion des Fournisseurs et Services Externes
- **Annuaire Fournisseurs :** Base de données des fournisseurs de matières premières et de matériel (coordonnées, historique d'achats, délais moyens de livraison, notes internes).
- **Gestion des Prestataires de Service (Livreurs) :** Suivi des coursiers et livreurs spécialisés dans le transport de produits fragiles. Enregistrement des frais de livraison associés à chaque course pour répercussion ou calcul de marge. Notation et historique des incidents.
- **Générateur de Liste de Courses :** Consolidation automatique des besoins en ingrédients sur une période donnée en fonction des commandes validées, avec prise en compte des stocks actuels et des seuils d'alerte, pour optimiser les achats de la semaine. Export PDF / impression.

### Module 5 : Gestion Comptable et Financière
Rigueur budgétaire et traçabilité des flux monétaires.
- **Paiements en ligne (optionnel) :** Intégration d'une passerelle de paiement (Mobile Money via API MTN MoMo / Orange Money, ou Stripe) pour permettre aux clients de payer l'acompte directement depuis un lien WhatsApp. Encaissements toujours confirmables manuellement en back-office.
- **Suivi des Encaissements :** Gestion des acomptes (ex: 50% à la commande) et des soldes, avec précision du mode de règlement (Mobile Money, Espèces, Virement). Chaque transaction est liée à une commande et à un utilisateur valideur.
- **Règle Stricte de Remboursement :** Par souci de transparence et de rigueur comptable, **les remboursements doivent s'effectuer et être tracés directement au niveau de la liste des transactions financières**, et non depuis la liste des commandes. Un remboursement génère une transaction de type "refund" liée à la transaction d'origine.
- **Facturation :** Génération de reçus et factures au format PDF (via Laravel DomPDF ou Browsershot) optimisés pour un partage direct et rapide en pièce jointe sur WhatsApp.
- **Suivi des Charges :** Enregistrement des dépenses d'exploitation (achats de matières premières, rémunération des livreurs, frais généraux). Catégorisation et export comptable.

---

## 4. Modules Complémentaires Recommandés (Valeur Ajoutée)

### Module 6 : Planning de Production & Calendrier Interactif
- Vue de type Calendrier ou Kanban récapitulant les tâches de l'atelier par jour (Cuisson, Montage, Décoration) en se basant sur les dates de livraison des commandes. Drag & drop pour réaffecter les jours.

### Module 7 : Raccourcis WhatsApp (Quick Share)
- Intégration de templates de messages configurables (ex: Confirmation de commande, Notification de mise en livraison, Demande d'acompte avec lien de paiement). Un clic ouvre WhatsApp Web ou l'application mobile avec le texte pré-rempli et le numéro du client.

### Module 8 : Tableau de Bord Analytique
- Suivi du Chiffre d'Affaires (journalier, mensuel, annuel), calcul de la marge réelle par gâteau (Prix de vente - Coût réel des ingrédients - Frais de livraison - Pertes), et palmarès des parfums, thèmes ou périodes les plus populaires. Export CSV des indicateurs.

---

## 5. Qualité, Tests & Stratégie de Validation

### Tests Unitaires et Feature (Obligatoire — seuil de qualité)
Le projet doit atteindre un **minimum de 80% de couverture de code** (chemins critiques) avant mise en production :
| Type | Outil | Cible |
|---|---|---|
| **Tests unitaires** | PHPUnit / Pest | Entités, Services, Actions, Form Requests |
| **Tests de feature** | PHPUnit + Laravel Dusk | Parcours métier complets (création commande → paiement → livraison) |
| **Tests de permission** | PHPUnit | Vérification de chaque gate/policy par rôle |
| **Tests de régression** | GitHub Actions (CI) | Exécution automatique à chaque push |
| **Tests de bout en bout** | Laravel Dusk | Scénarios Livewire critiques (création d'étage de gâteau dynamique) |

### Qualité du code
- Analyse statique : **Laravel Pint** (PSR-12) + **PHPStan** (niveau 5 minimum)
- Pas de `dd()` / `ray()` / `dump()` dans le code versionné (CI doit échouer si présent)
- Convention de nommage : ressources RESTful (même sans API), relations Eloquent explicites

---

## 6. Infrastructure, Déploiement & CI/CD

### Environnements
| Environnement | Usage | Accès |
|---|---|---|
| **Development** (local) | Développement, Sail (Docker) | Développeurs |
| **Staging** (VPS / Forge) | Tests utilisateurs finaux, QA | Équipe interne + bêta-testeurs |
| **Production** (VPS / Forge) | Application live | Équipe uniquement |

### Pipeline CI/CD (GitHub Actions)
1. **Push sur `main` ou `develop`** → Tests unitaires + feature (PHPUnit, Dusk)
2. **Analyse statique** → Pint (lint), PHPStan
3. **Build assets** → NPM run build
4. **Déploiement automatique** → Laravel Envoyer / Forge (zero-downtime)

### Sauvegardes
- **Base de données :** Sauvegarde automatique quotidienne (PostgreSQL pg_dump) avec rétention 30 jours, chiffrée au repos.
- **Fichiers uploadés :** Réplication S3 cross-region ou backup quotidien.
- **Procédure de restauration :** Documentée et testée trimestriellement.

### Conformité & Sécurité
- **RGPD :** Déclaration de traitement des données clients (nom, téléphone, adresse). Bouton de suppression de compte client (anonymisation). Durée de conservation : 3 ans après dernière commande (obligation comptable).
- **HTTPS :** Certificat Let's Encrypt (auto-renouvellement via Forge / Certbot).
- **Rate limiting :** Laravel throttle sur les endpoints de connexion et création de commande.
- **Logs :** Toutes les actions financières (paiement, remboursement, validation) sont loguées dans une table `audit_logs` non modifiable (insert-only).

---

## 7. Modélisation de Données (Aperçu)

### Entités principales (relationnelles)
| Table | Description | Liens clés |
|---|---|---|
| `users` | Utilisateurs de l'app (rôles via `role_user`) | — |
| `clients` | Clients finaux | `clients.phone` (unique) |
| `orders` | Commandes | `orders.client_id`, `orders.status` (enum) |
| `cake_tiers` | Étage d'un gâteau (polymorphique) | `cake_tier.order_id`, parfums, dimensions en JSON ? |
| `recipes` | Fiches techniques | `recipes.id` → `recipe_ingredients.recipe_id` |
| `ingredients` | Matières premières | `ingredients.unit` (enum), seuil d'alerte |
| `inventory_movements` | Mouvements de stock | `movement.type` (in/out/adjust/loss) |
| `transactions` | Transactions financières | `transaction.type` (payment/refund/fee), lien `order_id` |
| `suppliers` | Fournisseurs | `supplier.id` → `purchase_orders` |
| `delivery_partners` | Livreurs | — |

### Contrainte forte
`transactions` et `inventory_movements` sont des **tables d'écriture seule** (append-only, pas de `UPDATE` / `DELETE` en production). Les corrections passent par une écriture compensatoire (contre-passation).

---

## 8. Critères d'Acceptation et Qualité
- **Ergonomie Mobile :** L'interface développée avec Flux UI 2 doit être 100% responsive pour permettre une saisie aussi bien sur ordinateur à l'atelier que sur smartphone lors des déplacements.
- **Performance :** L'utilisation combinée de Laravel 13 et Livewire 4 / Volt doit garantir une réactivité optimale et des mises à jour d'état instantanées sans rechargement complet de page lors du paramétrage des étages de gâteaux.
- **Couverture de tests :** ≥ 80% sur les modules financiers, commandes et stocks.
- **Disponibilité :** Objectif 99,5% en production (maintenance programmée hors heures ouvrées).

---

## 9. Limites et Exclusions (Périmètre V3)
- L'intégration **WhatsApp Business API** (envoi automatique de messages, webhook de réception) est **optionnelle et fera l'objet d'un second lot** si validée. Le MVP repose sur des liens `wa.me/` générés manuellement.
- Pas de boutique en ligne / e-commerce. L'application est un **Back-Office interne uniquement**.
- Pas d'application mobile native. La PWA (Progressive Web App) pourra être étudiée si le besoin se confirme.
