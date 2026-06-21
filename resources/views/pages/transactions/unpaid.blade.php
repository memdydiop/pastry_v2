<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Transaction;
use App\Traits\WithSorting;
use Illuminate\Validation\Rule;

new #[Title('Paiements - Commandes non soldées')] class extends Component {
    use WithPagination;
    use WithSorting;

    public $search = '';
    public $methodFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = PER_PAGE;

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
    public function updatedMethodFilter() { $this->resetPage(); }
    public function updatedDateFrom() { $this->resetPage(); }
    public function updatedDateTo() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'methodFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    // -------------------------------------------------------------------------
    // Ouverture des modales
    // FIX : standardisation sur wire:click direct pour les deux modales
    // (openCancelModal était déjà en wire:click, openEditModal utilisait #[On]
    // — deux patterns différents pour la même chose, source de confusion).
    // -------------------------------------------------------------------------

    public function openCancelModal(int $transactionId): void
    {
        // FIX Sécurité : vérifier que l'utilisateur a le droit d'annuler
        // avant même de charger la transaction.
        $this->authorize('cancel-transaction');

        $this->cancellingTransaction = Transaction::with('order', 'user')->findOrFail($transactionId);
        $this->cancellationReason    = '';
        $this->showCancelModal       = true;
    }

    public function openEditModal(int $transactionId): void
    {
        // FIX Sécurité : autorisation explicite avant chargement.
        $this->authorize('edit-transaction');

        $this->editingTransaction = Transaction::with('order', 'user')->findOrFail($transactionId);
        $this->editAmount         = floatval($this->editingTransaction->amount);
        $this->editMethod         = $this->editingTransaction->payment_method?->value ?? '';
        $this->editExternalRef    = $this->editingTransaction->external_ref ?? '';
        $this->editNotes          = $this->editingTransaction->notes ?? '';
        $this->showEditModal      = true;
    }

    public function processCancel(): void
    {
        // FIX Sécurité : double vérification côté action (defense in depth).
        $this->authorize('cancel-transaction');

        $this->validate([
            'cancellationReason' => 'required|string|min:3|max:1000',
        ]);

        $this->cancellingTransaction->cancel($this->cancellationReason);

        $this->dispatch('toast', variant: 'success', heading: 'Paiement annulé.');
        $this->showCancelModal       = false;
        $this->cancellingTransaction = null;
    }

    public function processEdit(): void
    {
        // FIX Sécurité : double vérification côté action.
        $this->authorize('edit-transaction');

        $this->validate([
            'editAmount'      => 'required|numeric|min:1',
            'editMethod'      => ['required', Rule::enum(PaymentMethod::class)],
            'editExternalRef' => 'nullable|string|max:255',
            'editNotes'       => 'nullable|string|max:1000',
        ]);

        $this->editingTransaction->edit([
            'amount'         => $this->editAmount,
            'payment_method' => $this->editMethod,
            'external_ref'   => $this->editExternalRef ?: null,
            'notes'          => $this->editNotes ?: null,
        ]);

        $this->dispatch('toast', variant: 'success', heading: 'Paiement modifié.');
        $this->showEditModal      = false;
        $this->editingTransaction = null;
    }

    public function with(): array
    {
        // -------------------------------------------------------------------------
        // FIX Performance : la sous-requête de calcul du solde était copiée/collée
        // 3 fois dans cette méthode. Elle est maintenant centralisée dans deux
        // scopes Eloquent sur Order : scopeWithOutstandingBalance() et
        // scopeWithOutstandingAmount(). L'appel devient lisible et maintenable.
        //
        // FIX N+1 : $txn->order->remaining_balance dans la boucle Blade déclenchait
        // 2 requêtes SQL par ligne (1 pour les paiements, 1 pour les remboursements).
        // Solution : on eager-load les orders avec leur outstanding_amount calculé
        // en SQL via scopeWithOutstandingAmount(), puis on le lit directement
        // depuis l'attribut sans requête supplémentaire.
        // -------------------------------------------------------------------------

        $query = Transaction::with([
                'order' => fn ($q) => $q->withOutstandingAmount(),
                'user',
                'editedBy',
            ])
            ->notCancelled()
            ->where('type', 'payment')
            ->whereHas('order', fn ($q) => $q->withOutstandingBalance());

        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->latest('paid_at');
        }

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('reference', 'like', '%' . $this->search . '%')
                  ->orWhereHas('order', fn ($o) => $o
                      ->where('reference', 'like', '%' . $this->search . '%')
                      ->orWhere('client_name', 'like', '%' . $this->search . '%'));
            });
        }

        if (! empty($this->methodFilter)) {
            $query->where('payment_method', $this->methodFilter);
        }

        if (! empty($this->dateFrom)) {
            $query->whereDate('paid_at', '>=', $this->dateFrom);
        }

        if (! empty($this->dateTo)) {
            $query->whereDate('paid_at', '<=', $this->dateTo);
        }

        return [
            'transactions'   => $query->paginate($this->perPage),
            'isFiltered'     => ! empty($this->search) || ! empty($this->methodFilter) || ! empty($this->dateFrom) || ! empty($this->dateTo),
            'paymentMethods' => PaymentMethod::cases(),
            // FIX : utilisation des scopes centralisés pour les métriques
            'totalPayments'  => Transaction::where('type', 'payment')
                ->notCancelled()
                ->whereHas('order', fn ($q) => $q->withOutstandingBalance())
                ->sum('amount'),
            'unsettledOrders' => Order::withOutstandingBalance()->count(),
            'totalOutstanding' => Order::withOutstandingBalance()
                ->withOutstandingAmount()
                ->get()
                ->sum('outstanding_amount'),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Commandes non soldées'"
        :subtitle="'Paiements enregistrés sur des commandes avec un solde restant dû.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Finance</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('transactions.index')" wire:navigate>Transactions</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Non soldées</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-amber-600 dark:text-amber-400 font-semibold uppercase tracking-wider">Commandes non soldées</flux:text>
                <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">
                    {{ $unsettledOrders }}
                </div>
            </div>
        </flux:card>

        <flux:card class="border border-red-200/80 dark:border-red-800/60 bg-red-50/50 dark:bg-red-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-red-600 dark:text-red-400 font-semibold uppercase tracking-wider">Solde total dû</flux:text>
                <div class="text-2xl font-black text-red-700 dark:text-red-300 mt-1">
                    {{ number_format($totalOutstanding ?? 0, 0, ',', ' ') }}
                    <span class="text-sm font-normal">FCFA</span>
                </div>
            </div>
        </flux:card>

        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-emerald-600 dark:text-emerald-400 font-semibold uppercase tracking-wider">Total Encaissements</flux:text>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">
                    {{ number_format($totalPayments, 0, ',', ' ') }}
                    <span class="text-sm font-normal">FCFA</span>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header :title="'Paiements des commandes non soldées'" :subtitle="'Encaissements partiels sur des commandes avec un reste à payer.'" />

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Rechercher transaction, commande..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
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
                    {{-- FIX : emoji 💰 remplacé par icône Flux accessible --}}
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mx-auto shadow-xs">
                        <flux:icon name="banknotes" class="size-6 text-zinc-400 dark:text-zinc-500" aria-hidden="true" />
                    </div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucun paiement trouvé</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">
                        Toutes les commandes sont soldées ou aucun paiement ne correspond à vos filtres.
                    </flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'paid_at'" :direction="$sortBy === 'paid_at' ? $sortDirection : null" wire:click="sort('paid_at')">Date</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'reference'" :direction="$sortBy === 'reference' ? $sortDirection : null" wire:click="sort('reference')">Référence</flux:table.column>
                        <flux:table.column>Commande / Client</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'amount'" :direction="$sortBy === 'amount' ? $sortDirection : null" wire:click="sort('amount')">Montant</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'payment_method'" :direction="$sortBy === 'payment_method' ? $sortDirection : null" wire:click="sort('payment_method')">Moyen</flux:table.column>
                        <flux:table.column>Solde restant</flux:table.column>
                        <flux:table.column>Enregistré par</flux:table.column>
                        <flux:table.column class="text-end">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($transactions as $txn)
                            <flux:table.row :key="$txn->id">

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
                                        <div class="text-[11px] text-amber-500 mt-0.5">
                                            Modifié {{ $txn->edited_at->format('d/m/Y H:i') }}
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
                                    <span class="font-bold text-zinc-900 dark:text-white">
                                        {{ number_format($txn->amount, 0, ',', ' ') }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400">FCFA</span>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge size="sm" variant="neutral" class="px-2 py-0.5">
                                        {{ $txn->payment_method?->label() ?? $txn->payment_method }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>
                                    {{--
                                        FIX N+1 : on lit outstanding_amount depuis l'order déjà
                                        eager-loadé avec scopeWithOutstandingAmount() — zéro requête
                                        supplémentaire par ligne au lieu de 2 par ligne.
                                    --}}
                                    @php $remaining = floatval($txn->order?->outstanding_amount ?? 0); @endphp
                                    <span class="font-semibold {{ $remaining > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                        {{ number_format($remaining, 0, ',', ' ') }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400">FCFA</span>
                                </flux:table.cell>

                                <flux:table.cell class="text-sm text-zinc-500">
                                    {{ $txn->user?->name ?? '—' }}
                                </flux:table.cell>

                                <flux:table.cell class="text-end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" class="cursor-pointer" title="Actions" />
                                        <flux:menu>
                                            {{-- FIX : openEditModal en wire:click direct, cohérent avec openCancelModal --}}
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $txn->id }})">
                                                Modifier
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="x-circle" wire:click="openCancelModal({{ $txn->id }})">
                                                Annuler
                                            </flux:menu.item>
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

    {{-- ------------------------------------------------------------------ --}}
    {{-- Modale : annulation de paiement                                     --}}
    {{-- ------------------------------------------------------------------ --}}
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

                <flux:textarea
                    wire:model="cancellationReason"
                    label="Motif d'annulation"
                    required
                    placeholder="Ex : Paiement enregistré par erreur, client n'a pas finalisé le paiement..."
                    rows="3"
                    size="sm"
                />

                <div class="flex gap-2 justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">Retour</flux:button>
                    <flux:button type="submit" variant="danger" class="cursor-pointer" icon="x-circle">
                        Confirmer l'annulation
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Modale : modification de paiement                                   --}}
    {{-- ------------------------------------------------------------------ --}}
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

                    <flux:input
                        wire:model="editExternalRef"
                        label="N° Transaction / Mobile (optionnel)"
                        placeholder="Ex : WAVE-123ABC, OM-456789..."
                        size="sm"
                    />

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
