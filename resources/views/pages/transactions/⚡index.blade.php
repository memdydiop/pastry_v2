<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Transaction;
use App\Traits\WithSorting;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

new #[Title('Transactions')] class extends Component {
    use WithPagination;
    use WithSorting;

    public $search = '';
    public $typeFilter = '';
    public $methodFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = PER_PAGE;

    public $showRefundModal = false;
    public $refundingTransaction = null;
    public $refundAmount = 0;
    public $refundMethod = '';
    public $refundReason = '';
    public $refundExternalRef = '';

    public $showCancelModal = false;
    public $cancellingTransaction = null;
    public $cancellationReason = '';

    public $showEditModal = false;
    public $editingTransaction = null;
    public $editAmount = 0;
    public $editMethod = '';
    public $editExternalRef = '';
    public $editNotes = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedTypeFilter() { $this->resetPage(); }
    public function updatedMethodFilter() { $this->resetPage(); }
    public function updatedDateFrom() { $this->resetPage(); }
    public function updatedDateTo() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'typeFilter', 'methodFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function openRefundModal(int $transactionId)
    {
        $this->refundingTransaction = Transaction::with('order', 'user')->findOrFail($transactionId);

        if ($this->refundingTransaction->type !== TransactionType::PAYMENT) {
            $this->dispatch('toast', variant: 'error', heading: 'Seules les transactions de type paiement peuvent être remboursées.');
            return;
        }

        if ($this->refundingTransaction->isCancelled()) {
            $this->dispatch('toast', variant: 'error', heading: 'Impossible de rembourser un paiement annulé.');
            return;
        }

        $alreadyRefunded = floatval($this->refundingTransaction->refunds()->sum('amount'));
        $maxRefund = floatval($this->refundingTransaction->amount) - $alreadyRefunded;

        if ($maxRefund <= 0) {
            $this->dispatch('toast', variant: 'error', heading: 'Cette transaction a déjà été intégralement remboursée.');
            return;
        }

        $this->refundAmount = $maxRefund;
        $this->refundMethod = 'Espèces';
        $this->refundReason = '';
        $this->refundExternalRef = '';
        $this->showRefundModal = true;
    }

    public function processRefund()
    {
        $this->validate([
            'refundAmount' => 'required|numeric|min:0.01',
            'refundMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'refundReason' => 'nullable|string|max:500',
            'refundExternalRef' => 'nullable|string|max:255',
        ]);

        $original = $this->refundingTransaction;
        $alreadyRefunded = floatval($original->refunds()->sum('amount'));
        $maxRefund = floatval($original->amount) - $alreadyRefunded;

        if ($this->refundAmount > $maxRefund) {
            $this->addError('refundAmount', "Le montant du remboursement ne peut pas dépasser " . number_format($maxRefund, 0, ',', ' ') . " FCFA.");
            return;
        }

        DB::transaction(function () use ($original) {
            $original->refunds()->create([
                'type' => TransactionType::REFUND,
                'reference' => Transaction::generateReference('Remb'),
                'order_id' => $original->order_id,
                'amount' => $this->refundAmount,
                'payment_method' => $this->refundMethod,
                'paid_at' => now(),
                'external_ref' => $this->refundExternalRef,
                'notes' => $this->refundReason,
                'user_id' => auth()->id(),
            ]);
        });

        $this->dispatch('toast', variant: 'success', heading: 'Remboursement enregistré.');
        $this->showRefundModal = false;
        $this->refundingTransaction = null;
    }

    public function openCancelModal(int $transactionId)
    {
        $this->cancellingTransaction = Transaction::with('order', 'user')->findOrFail($transactionId);
        $this->cancellationReason = '';
        $this->showCancelModal = true;
    }

    public function processCancel()
    {
        $this->validate([
            'cancellationReason' => 'required|string|min:3|max:1000',
        ]);

        $this->cancellingTransaction->cancel($this->cancellationReason);

        $this->dispatch('toast', variant: 'success', heading: 'Paiement annulé.');
        $this->showCancelModal = false;
        $this->cancellingTransaction = null;
    }

    public function openEditModal(int $transactionId)
    {
        $this->editingTransaction = Transaction::with('order', 'user')->findOrFail($transactionId);
        $this->editAmount = floatval($this->editingTransaction->amount);
        $this->editMethod = $this->editingTransaction->payment_method?->value ?? '';
        $this->editExternalRef = $this->editingTransaction->external_ref ?? '';
        $this->editNotes = $this->editingTransaction->notes ?? '';
        $this->showEditModal = true;
    }

    public function processEdit()
    {
        $this->validate([
            'editAmount' => 'required|numeric|min:1',
            'editMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'editExternalRef' => 'nullable|string|max:255',
            'editNotes' => 'nullable|string|max:1000',
        ]);

        $this->editingTransaction->edit([
            'amount' => $this->editAmount,
            'payment_method' => $this->editMethod,
            'external_ref' => $this->editExternalRef ?: null,
            'notes' => $this->editNotes ?: null,
        ]);

        $this->dispatch('toast', variant: 'success', heading: 'Paiement modifié.');
        $this->showEditModal = false;
        $this->editingTransaction = null;
    }

    public function with(): array
    {
        $query = Transaction::with(['order', 'user', 'parentTransaction', 'editedBy'])->withSum('refunds', 'amount');

        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->latest('paid_at');
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('reference', 'like', '%' . $this->search . '%')
                  ->orWhereHas('order', fn($o) => $o->where('reference', 'like', '%' . $this->search . '%')
                      ->orWhere('client_name', 'like', '%' . $this->search . '%'));
            });
        }

        if (!empty($this->typeFilter)) {
            $query->where('type', $this->typeFilter);
        }

        if (!empty($this->methodFilter)) {
            $query->where('payment_method', $this->methodFilter);
        }

        if (!empty($this->dateFrom)) {
            $query->whereDate('paid_at', '>=', $this->dateFrom);
        }

        if (!empty($this->dateTo)) {
            $query->whereDate('paid_at', '<=', $this->dateTo);
        }

        return [
            'transactions' => $query->paginate($this->perPage),
            'isFiltered' => !empty($this->search) || !empty($this->typeFilter) || !empty($this->methodFilter) || !empty($this->dateFrom) || !empty($this->dateTo),
            'paymentMethods' => PaymentMethod::cases(),
            'totalPayments' => Transaction::notCancelled()->where('type', 'payment')->sum('amount'),
            'totalRefunds' => Transaction::notCancelled()->where('type', 'refund')->sum('amount'),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Transactions'"
        :subtitle="'Suivi des encaissements, remboursements et flux financiers.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Finance</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Transactions</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-emerald-600 dark:text-emerald-400 font-semibold uppercase tracking-wider">Total Encaissements</flux:text>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">
                    {{ number_format($totalPayments, 0, ',', ' ') }} <span class="text-sm font-normal">FCFA</span>
                </div>
            </div>
        </flux:card>

        <flux:card class="border border-rose-200/80 dark:border-rose-800/60 bg-rose-50/50 dark:bg-rose-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-rose-600 dark:text-rose-400 font-semibold uppercase tracking-wider">Total Remboursements</flux:text>
                <div class="text-2xl font-black text-rose-700 dark:text-rose-300 mt-1">
                    {{ number_format($totalRefunds, 0, ',', ' ') }} <span class="text-sm font-normal">FCFA</span>
                </div>
            </div>
        </flux:card>

        <flux:card class="border border-blue-200/80 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-blue-600 dark:text-blue-400 font-semibold uppercase tracking-wider">Solde Net</flux:text>
                <div class="text-2xl font-black text-blue-700 dark:text-blue-300 mt-1">
                    {{ number_format($totalPayments - $totalRefunds, 0, ',', ' ') }} <span class="text-sm font-normal">FCFA</span>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header :title="'Historique des Transactions'" :subtitle="'Liste complète des flux financiers enregistrés.'" />

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Rechercher transaction, commande..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
                <flux:select wire:model.live="typeFilter" placeholder="Tous les types" size="sm" class="w-full sm:w-40">
                    <option value="payment">Paiements</option>
                    <option value="refund">Remboursements</option>
                </flux:select>

                <flux:select wire:model.live="methodFilter" placeholder="Tous les moyens" size="sm" class="w-full sm:w-40">
                    @foreach($paymentMethods as $m)
                        <option value="{{ $m->value }}">{{ $m->label() }}</option>
                    @endforeach
                </flux:select>

                <flux:input type="date" wire:model.live="dateFrom" placeholder="Du" size="sm" class="w-full sm:w-36" />
                <flux:input type="date" wire:model.live="dateTo" placeholder="Au" size="sm" class="w-full sm:w-36" />
            </x-card.table-filters>

            @if($transactions->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 flex items-center justify-center text-xl mx-auto shadow-xs">
                        💰
                    </div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucune transaction trouvée</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">Aucun flux financier enregistré ou ne correspondant à vos filtres.</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'paid_at'" :direction="$sortBy === 'paid_at' ? $sortDirection : null" wire:click="sort('paid_at')">Date</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'reference'" :direction="$sortBy === 'reference' ? $sortDirection : null" wire:click="sort('reference')">Référence</flux:table.column>
                        <flux:table.column>Commande / Client</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'amount'" :direction="$sortBy === 'amount' ? $sortDirection : null" wire:click="sort('amount')">Montant</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'payment_method'" :direction="$sortBy === 'payment_method' ? $sortDirection : null" wire:click="sort('payment_method')">Moyen</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortBy === 'type' ? $sortDirection : null" wire:click="sort('type')">Type</flux:table.column>
                        <flux:table.column>Statut</flux:table.column>
                        <flux:table.column>Enregistré par</flux:table.column>
                        <flux:table.column class="text-end">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($transactions as $txn)
                            <flux:table.row :key="$txn->id" class="{{ $txn->isCancelled() ? 'opacity-60' : '' }}">
                                <flux:table.cell class="whitespace-nowrap">
                                    <div class="text-sm font-medium">{{ $txn->paid_at->format('d/m/Y') }}</div>
                                    <div class="text-xs text-zinc-400">{{ $txn->paid_at->format('H:i') }}</div>
                                </flux:table.cell>

                                <flux:table.cell class="font-mono text-xs">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $txn->reference ?? '—' }}</div>
                                    @if($txn->external_ref)
                                        <div class="text-zinc-400 text-[11px] mt-0.5">{{ $txn->external_ref }}</div>
                                    @endif
                                    @if($txn->edited_at)
                                        <div class="text-[11px] text-amber-500 mt-0.5 flex items-center gap-1">
                                            Modifié {{ $txn->edited_at->format('d/m/Y H:i') }}
                                        </div>
                                    @endif
                                    @if($txn->type === TransactionType::REFUND && $txn->parentTransaction)
                                        <div class="text-[11px] text-rose-500 mt-0.5 flex items-center gap-1">
                                            <span>←</span>
                                            <span>{{ $txn->parentTransaction->reference ?? 'Paiement #'.$txn->parent_transaction_id }}</span>
                                        </div>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="text-sm font-medium">
                                        <a href="{{ route('orders.show', $txn->order) }}" class="hover:underline text-indigo-600 dark:text-indigo-400">
                                            {{ $txn->order?->reference ?? '—' }}
                                        </a>
                                    </div>
                                    <div class="text-xs text-zinc-400">{{ $txn->order?->client_name ?? '—' }}</div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <span class="font-bold {{ $txn->type === TransactionType::REFUND ? 'text-rose-600' : 'text-zinc-900 dark:text-white' }}">
                                        {{ $txn->type === TransactionType::REFUND ? '−' : '' }}{{ number_format($txn->amount, 0, ',', ' ') }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400">FCFA</span>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge size="sm" variant="neutral" class="px-2 py-0.5">
                                        {{ $txn->payment_method?->label() ?? $txn->payment_method }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @php
                                        $typeBadge = match($txn->type->value) {
                                            'payment' => 'success',
                                            'refund' => 'danger',
                                            'fee' => 'warning',
                                            default => 'neutral'
                                        };
                                    @endphp
                                    <flux:badge :variant="$typeBadge" size="sm" class="px-2 py-0.5">
                                        {{ $txn->type->value === 'payment' ? 'Paiement' : ($txn->type->value === 'refund' ? 'Remboursement' : 'Frais') }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if($txn->isCancelled())
                                        <flux:badge size="sm" variant="danger" class="px-2 py-0.5" title="{{ $txn->cancellation_reason }}">
                                            Annulé
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" variant="success" class="px-2 py-0.5">
                                            Actif
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="text-sm text-zinc-500">{{ $txn->user?->name ?? '—' }}</flux:table.cell>

                                <flux:table.cell class="text-end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" class="cursor-pointer" title="Actions" />
                                        <flux:menu>
                                            @if(!$txn->isCancelled() && $txn->type === TransactionType::PAYMENT)
                                                @if(floatval($txn->refunds_sum_amount ?? 0) < floatval($txn->amount))
                                                    <flux:menu.item icon="arrow-uturn-left" wire:click="openRefundModal({{ $txn->id }})">
                                                        Rembourser
                                                    </flux:menu.item>
                                                @endif
                                                <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $txn->id }})">
                                                    Modifier
                                                </flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item icon="x-circle" wire:click="openCancelModal({{ $txn->id }})">
                                                    Annuler
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($transactions->hasPages())
                    <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $transactions->links() }}
                    </div>
                @endif
            @endif
        </x-card.card-body>
    </flux:card>

    <flux:modal name="refund-modal" wire:model="showRefundModal" class="max-w-lg space-y-6">
        @if($refundingTransaction)
            <form wire:submit.prevent="processRefund" class="space-y-6">
                <div>
                    <flux:heading size="lg">Confirmer le remboursement</flux:heading>
                    <flux:subheading>Ce remboursement sera tracé dans la liste des transactions.</flux:subheading>
                </div>

                <div class="bg-zinc-50 dark:bg-zinc-950/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Transaction d'origine</span>
                        <span class="font-mono text-xs">{{ $refundingTransaction->reference }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Commande</span>
                        <span>{{ $refundingTransaction->order?->reference }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Client</span>
                        <span>{{ $refundingTransaction->order?->client_name }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-semibold border-t border-zinc-200 dark:border-zinc-800 pt-2 mt-2">
                        <span>Montant original</span>
                        <span>{{ number_format($refundingTransaction->amount, 0, ',', ' ') }} FCFA</span>
                    </div>
                    @php
                        $alreadyRefunded = floatval($refundingTransaction->refunds()->sum('amount'));
                    @endphp
                    @if($alreadyRefunded > 0)
                        <div class="flex justify-between text-sm text-rose-600">
                            <span>Déjà remboursé</span>
                            <span>−{{ number_format($alreadyRefunded, 0, ',', ' ') }} FCFA</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-emerald-600">
                            <span>Remboursable</span>
                            <span>{{ number_format(floatval($refundingTransaction->amount) - $alreadyRefunded, 0, ',', ' ') }} FCFA</span>
                        </div>
                    @endif
                </div>

                <div class="space-y-4">
                    <flux:input type="number" wire:model="refundAmount" label="Montant à rembourser" required size="sm"
                        :max="floatval($refundingTransaction->amount) - floatval($refundingTransaction->refunds()->sum('amount'))" />

                    <flux:select wire:model="refundMethod" label="Moyen de remboursement" size="sm">
                        @foreach(App\Enums\PaymentMethod::cases() as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="refundExternalRef" label="N° Transaction / Mobile (optionnel)" placeholder="Ex: WAVE-123ABC, OM-456789..." size="sm" />

                    <flux:textarea wire:model="refundReason" label="Motif (optionnel)" placeholder="Ex: Annulation commande, erreur de paiement..." rows="2" size="sm" />
                </div>

                <div class="flex gap-2 justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('showRefundModal', false)">Annuler</flux:button>
                    <flux:button type="submit" variant="danger" class="cursor-pointer" icon="arrow-uturn-left">
                        Confirmer le remboursement
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="cancel-modal" wire:model="showCancelModal" class="max-w-lg space-y-6">
        @if($cancellingTransaction)
            <form wire:submit.prevent="processCancel" class="space-y-6">
                <div>
                    <flux:heading size="lg">Annuler le paiement</flux:heading>
                    <flux:subheading>Ce paiement sera marqué comme annulé et ne sera plus comptabilisé dans le solde de la commande.</flux:subheading>
                </div>

                <div class="bg-zinc-50 dark:bg-zinc-950/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Transaction</span>
                        <span class="font-mono text-xs">{{ $cancellingTransaction->reference }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Commande</span>
                        <span>{{ $cancellingTransaction->order?->reference }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Client</span>
                        <span>{{ $cancellingTransaction->order?->client_name }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-semibold border-t border-zinc-200 dark:border-zinc-800 pt-2 mt-2">
                        <span>Montant</span>
                        <span>{{ number_format($cancellingTransaction->amount, 0, ',', ' ') }} FCFA</span>
                    </div>
                </div>

                <flux:textarea wire:model="cancellationReason" label="Motif d'annulation" required
                    placeholder="Ex: Paiement enregistré par erreur, client n'a pas finalisé le paiement..."
                    rows="3" size="sm" />

                <div class="flex gap-2 justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">Retour</flux:button>
                    <flux:button type="submit" variant="danger" class="cursor-pointer" icon="x-circle">
                        Confirmer l'annulation
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="edit-modal" wire:model="showEditModal" class="max-w-lg space-y-6">
        @if($editingTransaction)
            <form wire:submit.prevent="processEdit" class="space-y-6">
                <div>
                    <flux:heading size="lg">Modifier le paiement</flux:heading>
                    <flux:subheading>Les anciennes valeurs seront conservées pour l'audit.</flux:subheading>
                </div>

                <div class="bg-zinc-50 dark:bg-zinc-950/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Transaction</span>
                        <span class="font-mono text-xs">{{ $editingTransaction->reference }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Commande</span>
                        <span>{{ $editingTransaction->order?->reference }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Client</span>
                        <span>{{ $editingTransaction->order?->client_name }}</span>
                    </div>
                </div>

                <div class="space-y-4">
                    <flux:input type="number" wire:model="editAmount" label="Montant" required size="sm" />

                    <flux:select wire:model="editMethod" label="Moyen de paiement" size="sm">
                        @foreach(App\Enums\PaymentMethod::cases() as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="editExternalRef" label="N° Transaction / Mobile (optionnel)" placeholder="Ex: WAVE-123ABC, OM-456789..." size="sm" />

                    <flux:textarea wire:model="editNotes" label="Notes (optionnel)" rows="2" size="sm" />
                </div>

                <div class="flex gap-2 justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Retour</flux:button>
                    <flux:button type="submit" variant="primary" class="cursor-pointer" icon="check">
                        Enregistrer les modifications
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
