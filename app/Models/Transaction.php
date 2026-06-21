<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    /**
     * FIX : les champs d'audit (cancelled_by, edited_by, etc.) étaient absents
     * de $fillable — update([...]) les ignorait silencieusement, donc aucune
     * trace d'annulation ou de modification n'était jamais persistée en base.
     */
    protected $fillable = [
        'type',
        'order_id',
        'parent_transaction_id',
        'amount',
        'payment_method',
        'paid_at',
        'reference',
        'external_ref',
        'notes',
        'user_id',
        // Champs d'annulation
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        // Champs d'édition / audit
        'edited_at',
        'edited_by',
        'edit_old_values',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'cancelled_at' => 'datetime',
            'edited_at' => 'datetime',
            'edit_old_values' => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Génération de référence
    // -------------------------------------------------------------------------

    /**
     * Génère une référence unique avec préfixe, mois courant et séquence.
     * La séquence est basée sur le compte mensuel — suffisant pour l'usage actuel.
     * Pour une garantie absolue d'unicité, utiliser une séquence PostgreSQL dédiée.
     */
    public static function generateReference(string $prefix): string
    {
        $count = static::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;

        return $prefix.'-'.now()->format('Ym').'-'.str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeByMethod(Builder $query, string $method): Builder
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at');
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    // -------------------------------------------------------------------------
    // Logique métier
    // -------------------------------------------------------------------------

    public function isCancelled(): bool
    {
        return ! is_null($this->cancelled_at);
    }

    /**
     * Annule la transaction en traçant le motif et l'auteur.
     * Les champs cancelled_by etc. sont maintenant dans $fillable — ils seront
     * bien persistés en base contrairement à l'ancienne version.
     */
    public function cancel(string $reason): void
    {
        $this->update([
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Modifie la transaction en conservant les anciennes valeurs pour l'audit.
     * Les champs d'audit sont maintenant dans $fillable — ils seront bien persistés.
     */
    public function edit(array $data): void
    {
        $oldValues = [
            'amount' => $this->amount,
            'payment_method' => $this->payment_method?->value,
            'external_ref' => $this->external_ref,
            'notes' => $this->notes,
        ];

        $this->update(array_merge($data, [
            'edited_at' => now(),
            'edited_by' => auth()->id(),
            'edit_old_values' => $oldValues,
        ]));
    }
}
