<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'reference',
        'contact_phone_2',
        'contact_phone_3',
        'cake_type',
        'tiers_count',
        'servings_count',
        'flavors_details',
        'decorations_details',
        'theme_description',
        'colors_requested',
        'inscription_text',
        'delivery_due_at',
        'delivery_address',
        'conservation_notes',
        'allergens',
        'notes',
        'total_amount',
        'status',
        'user_id',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'delivery_due_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'tiers_count' => 'integer',
        'servings_count' => 'integer',
        'status' => OrderStatus::class,
        'cancelled_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Attributs calculés (JSON → tableau PHP)
    // -------------------------------------------------------------------------

    protected function flavorsDetails(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => is_string($value) ? $value : json_encode($value),
        );
    }

    protected function decorationsDetails(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => is_string($value) ? $value : json_encode($value),
        );
    }

    /**
     * Résumé lisible des parfums pour l'affichage en vue liste.
     * Évite d'afficher "Array" dans les templates Blade.
     */
    public function getFlavorsSummaryAttribute(): string
    {
        $details = $this->flavors_details;

        if (empty($details)) {
            return '—';
        }

        // Si c'est un tableau associatif de niveaux [['flavor' => '...'], ...]
        if (isset($details[0]) && is_array($details[0])) {
            return collect($details)
                ->map(fn ($tier) => $tier['flavor'] ?? null)
                ->filter()
                ->implode(', ');
        }

        // Fallback : chaîne brute
        return is_string($details) ? $details : implode(', ', (array) $details);
    }

    // -------------------------------------------------------------------------
    // Boot — événements du cycle de vie
    // FIX : la logique de statusLog est déplacée dans un Observer dédié.
    //       Voir App\Observers\OrderObserver pour la création de statusLogs.
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order) {
            if (auth()->check() && ! $order->user_id) {
                $order->user_id = auth()->id();
            }

            if (! $order->reference) {
                $datePart = now()->format('Ym');
                if (DB::getDriverName() === 'pgsql') {
                    $seq = DB::selectOne("SELECT nextval('cmd_seq') AS val")->val;
                } else {
                    $seq = DB::table('orders')->max('id') + 1;
                }
                $order->reference = 'CMD-'.$datePart.'-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
            }
        });

        // FIX : le hook updating est retiré d'ici.
        // Le log de changement de statut est géré par OrderObserver::updated()
        // pour séparer les responsabilités et permettre d'envoyer le job en queue.
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(OrderLevel::class)->orderBy('level_number');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(OrderImage::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // -------------------------------------------------------------------------
    // Accesseurs financiers
    // -------------------------------------------------------------------------

    /**
     * Total réellement encaissé (paiements non annulés - remboursements non annulés).
     */
    public function getTotalPaidAttribute(): float
    {
        $payments = $this->transactions()
            ->notCancelled()
            ->where('type', TransactionType::PAYMENT->value)
            ->sum('amount');

        $refunds = $this->transactions()
            ->notCancelled()
            ->where('type', TransactionType::REFUND->value)
            ->sum('amount');

        return (float) $payments - (float) $refunds;
    }

    /**
     * Reste à percevoir sur la commande.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->total_paid);
    }

    /**
     * Scope Eloquent réutilisable pour filtrer les commandes avec un solde restant.
     * Centralise la sous-requête SQL pour éviter la duplication dans les vues.
     *
     * Exemple d'usage :
     *   Order::withOutstandingBalance()->count();
     *   Order::withOutstandingBalance()->selectRaw('SUM(...)')->value('outstanding');
     */
    public function scopeWithOutstandingBalance(Builder $query): Builder
    {
        $subQuery = "
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN t.type = 'payment' THEN t.amount
                        WHEN t.type = 'refund'  THEN -t.amount
                        ELSE 0
                    END
                ),
                0
            )
            FROM transactions t
            WHERE t.order_id = orders.id
              AND t.cancelled_at IS NULL
        ";

        return $query->whereRaw("total_amount > ({$subQuery})");
    }

    /**
     * Ajoute le montant du solde restant directement en SELECT
     * pour éviter le N+1 dans les listes paginées.
     *
     * Exemple d'usage :
     *   Order::withOutstandingBalance()->withOutstandingAmount()->get()
     *   => $order->outstanding_amount disponible sans requête supplémentaire
     */
    public function scopeWithOutstandingAmount(Builder $query): Builder
    {
        $subQuery = "
            total_amount - COALESCE(
                (SELECT SUM(
                    CASE
                        WHEN t.type = 'payment' THEN t.amount
                        WHEN t.type = 'refund'  THEN -t.amount
                        ELSE 0
                    END
                )
                FROM transactions t
                WHERE t.order_id = orders.id
                  AND t.cancelled_at IS NULL),
                0
            )
        ";

        return $query->selectRaw("orders.*, GREATEST(0, {$subQuery}) AS outstanding_amount");
    }

    // -------------------------------------------------------------------------
    // Logique métier — annulation
    // -------------------------------------------------------------------------

    public function isCancelled(): bool
    {
        return ! is_null($this->cancelled_at);
    }

    /**
     * Retourne la liste des raisons bloquant l'annulation.
     * Tableau vide = annulation autorisée.
     *
     * FIX : la condition temporelle était inversée.
     * L'ancienne vérification utilisait isFuture() + diffInHours() ce qui
     * produisait un résultat incorrect pour les livraisons passées.
     * now()->diffInHours($date) retourne la valeur absolue correcte.
     */
    public function canBeCancelled(): array
    {
        $errors = [];

        if ($this->isCancelled() || $this->status === OrderStatus::ANNULÉE) {
            $errors[] = 'Cette commande est déjà annulée.';
        }

        if ($this->status === OrderStatus::LIVRÉE) {
            $errors[] = 'Impossible d\'annuler une commande déjà livrée.';
        }

        // FIX : utilisation de now()->diffInHours() avec valeur absolue,
        // valable que la date soit passée ou future.
        if ($this->delivery_due_at && now()->diffInHours($this->delivery_due_at, true) < 24) {
            $errors[] = 'Impossible d\'annuler une commande à moins de 24 heures de la livraison.';
        }

        // Avertissement métier : commande déjà en production (matières consommées).
        if ($this->status === OrderStatus::EN_PRODUCTION) {
            $errors[] = 'Attention : cette commande est déjà en production. Les matières premières engagées ne seront pas restituées automatiquement au stock.';
        }

        return $errors;
    }

    /**
     * Annule la commande et crée les remboursements associés.
     *
     * FIX 1 : lockForUpdate() évite la race condition si deux requêtes
     *          arrivent simultanément sur la même commande.
     * FIX 2 : whereDoesntHave('refunds') évite de créer des remboursements
     *          doublons si un paiement a déjà été remboursé.
     * FIX 3 : payment_method propagé dans le remboursement.
     *
     * NOTE CDC : conformément au cahier des charges, les remboursements sont
     * créés automatiquement ici mais restent visibles et traçables depuis la
     * liste des transactions financières. Si le métier exige une validation
     * manuelle par le Comptable, remplacer la création directe par un statut
     * intermédiaire "à rembourser" et notifier le Comptable.
     */
    public function cancel(string $reason): void
    {
        $errors = $this->canBeCancelled();

        // On retire les avertissements (non bloquants) des erreurs bloquantes
        $blockingErrors = array_filter($errors, fn ($e) => ! str_starts_with($e, 'Attention'));

        if (! empty($blockingErrors)) {
            throw new \RuntimeException(implode(' ', $blockingErrors));
        }

        DB::transaction(function () use ($reason) {
            // FIX : lockForUpdate() — verrou pessimiste pendant la transaction
            $payments = $this->transactions()
                ->lockForUpdate()
                ->notCancelled()
                ->where('type', TransactionType::PAYMENT->value)
                // FIX : exclure les paiements déjà remboursés (évite les doublons)
                ->whereDoesntHave('refunds', fn ($q) => $q->notCancelled())
                ->get();

            foreach ($payments as $payment) {
                $this->transactions()->create([
                    'type' => TransactionType::REFUND->value,
                    'parent_transaction_id' => $payment->id,
                    'amount' => $payment->amount,
                    // FIX : on propage le mode de paiement d'origine
                    'payment_method' => $payment->payment_method?->value,
                    'paid_at' => now(),
                    'reference' => Transaction::generateReference('Remb'),
                    'notes' => 'Remboursement automatique — annulation commande '.$this->reference,
                    'user_id' => auth()->id(),
                ]);
            }

            $this->update([
                'status' => OrderStatus::ANNULÉE,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => $reason,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Scopes globaux
    // -------------------------------------------------------------------------

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at');
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at')
            ->where('status', '!=', OrderStatus::ANNULÉE->value);
    }
}
