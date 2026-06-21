<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\Order;
use App\Traits\WithSorting;

new #[Title('Suivi des Commandes')] class extends Component {
    use WithPagination;
    use WithSorting;

    public $search = '';
    public $statusFilter = '';
    public $perPage = PER_PAGE;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    /**
     * FIX UX : le corps vide fonctionnait grâce au re-render automatique de
     * Livewire, mais c'était trompeur à la lecture. Le commentaire explicite
     * l'intention pour les développeurs suivants.
     */
    #[On('order-saved')]
    public function refreshOrders(): void
    {
        // Livewire re-rend automatiquement le composant à la réception de
        // cet événement — la liste est ainsi rafraîchie sans rechargement de page.
    }

    public function openCreateModal(): void
    {
        $this->dispatch('open-order-modal');
    }

    public function with(): array
    {
        $query = Order::with('client')
            // FIX : withSum('transactions', 'amount') additionnait TOUTES les
            // transactions (annulées, remboursements inclus) — montant affiché faux.
            // On remplace par deux agrégats distincts et filtrés :
            ->withSum(
                ['transactions as paid_sum' => fn ($q) => $q
                    ->where('type', 'payment')
                    ->whereNull('cancelled_at')
                ],
                'amount'
            )
            ->withSum(
                ['transactions as refund_sum' => fn ($q) => $q
                    ->where('type', 'refund')
                    ->whereNull('cancelled_at')
                ],
                'amount'
            );

        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->latest('id');
        }

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('reference', 'like', '%' . $this->search . '%')
                  ->orWhere('client_name', 'like', '%' . $this->search . '%')
                  ->orWhere('client_phone', 'like', '%' . $this->search . '%')
                  ->orWhere('cake_type', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', fn($q) => $q->where('name', 'like', '%' . $this->search . '%')->orWhere('phone', 'like', '%' . $this->search . '%'));
            });
        }

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        return [
            'orders'     => $query->paginate($this->perPage),
            'isFiltered' => ! empty($this->search) || ! empty($this->statusFilter),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading :title="'Suivi des Commandes'" :subtitle="'Consultez l\'état d\'avancement, les livraisons du jour et gérez les fiches de fabrication.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Dashboards</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Commandes</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <flux:card class="relative overflow-hidden border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header :title="'Suivi des Commandes'" :subtitle="'Liste des commandes et leur état d\'avancement.'">
            <x-slot:menu>
                <flux:menu.item icon="plus" wire:click="openCreateModal" x-on:click="$flux.modal('order-form-modal').show()" class="cursor-pointer">
                    Nouvelle commande
                </flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Rechercher une commande, client..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
                <flux:select wire:model.live="statusFilter" placeholder="Tous les statuts" size="sm" class="w-full sm:w-44">
                    @foreach(App\Enums\OrderStatus::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </flux:select>
            </x-card.table-filters>

            @if($orders->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    {{-- FIX : emoji remplacé par une icône Flux accessible --}}
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mx-auto shadow-xs">
                        <flux:icon name="clipboard-document-list" class="size-6 text-zinc-400 dark:text-zinc-500" aria-hidden="true" />
                    </div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucune commande trouvée</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">
                        Aucune commande enregistrée ou ne correspondant à votre recherche.
                    </flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="pl-6" sortable :sorted="$sortBy === 'reference'" :direction="$sortBy === 'reference' ? $sortDirection : null" wire:click="sort('reference')">Référence</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'client_name'" :direction="$sortBy === 'client_name' ? $sortDirection : null" wire:click="sort('client_name')">Client</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'cake_type'" :direction="$sortBy === 'cake_type' ? $sortDirection : null" wire:click="sort('cake_type')">Désignation Gâteau</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'delivery_due_at'" :direction="$sortBy === 'delivery_due_at' ? $sortDirection : null" wire:click="sort('delivery_due_at')">Date Livraison</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'total_amount'" :direction="$sortBy === 'total_amount' ? $sortDirection : null" wire:click="sort('total_amount')">Montant Total</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortBy === 'status' ? $sortDirection : null" wire:click="sort('status')">Statut</flux:table.column>
                        <flux:table.column class="pr-6 text-end">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($orders as $order)
                            <flux:table.row :key="$order->id">

                                <flux:table.cell class="pl-6 font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $order->reference }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $order->client->name }}</div>
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $order->client->phone }}</div>
                                    @if($order->contact_phone_2)
                                        <div class="text-[10px] text-zinc-400">Alt: {{ $order->contact_phone_2 }}</div>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="text-sm">
                                        <span class="font-medium">{{ $order->cake_type ?? 'Gâteau classique' }}</span>
                                        @if($order->tiers_count > 1)
                                            <span class="text-xs  text-indigo-600 dark:text-indigo-400 font-normal ml-1">
                                                ({{ $order->tiers_count }} étages)
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="text-xs text-zinc-400 truncate max-w-xs">
                                        {{ $order->flavors_summary }}
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="whitespace-nowrap">
                                    <div class="text-sm font-medium">
                                        {{ $order->delivery_due_at?->format('d/m/Y') ?? '—' }}
                                    </div>
                                    <div class="text-xs text-zinc-400">
                                        à {{ $order->delivery_due_at?->format('H:i') ?? '—' }}
                                    </div>
                                </flux:table.cell>

                                @php
                                    // FIX : calcul du montant payé depuis les deux agrégats filtrés
                                    // (paid_sum = paiements non annulés, refund_sum = remboursements non annulés)
                                    $paid      = max(0, floatval($order->paid_sum ?? 0) - floatval($order->refund_sum ?? 0));
                                    $remaining = max(0, floatval($order->total_amount) - $paid);
                                @endphp
                                <flux:table.cell>
                                    <div class="font-bold text-zinc-900 dark:text-white">
                                        {{ number_format($order->total_amount, 0, ',', ' ') }}
                                        <span class="text-[10px] font-normal text-zinc-400">FCFA</span>
                                    </div>
                                    <div class="text-[11px] mt-0.5 space-x-2">
                                        <span class="text-emerald-600 dark:text-emerald-400">
                                            Payé {{ number_format($paid, 0, ',', ' ') }}
                                        </span>
                                        @if($remaining > 0)
                                            <span class="text-rose-500">
                                                Reste {{ number_format($remaining, 0, ',', ' ') }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge :variant="$order->status?->badgeVariant() ?? 'neutral'" size="sm" class="px-2 py-0.5">
                                        {{ $order->status?->label() ?? $order->status }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell class="pr-6 text-end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" class="cursor-pointer" title="Actions" />
                                        <flux:menu>
                                            @php $whatsappLink = \App\Helpers\WhatsApp::linkForOrder($order, 'order_in_progress'); @endphp
                                            @if($whatsappLink)
                                                <flux:menu.item icon="chat-bubble-left"
                                                    :href="$whatsappLink"
                                                    target="_blank">
                                                    WhatsApp
                                                </flux:menu.item>
                                            @endif
                                            <flux:menu.item icon="eye"
                                                :href="route('orders.show', $order->id)"
                                                wire:navigate>
                                                Voir le détail
                                            </flux:menu.item>
                                            <flux:menu.item icon="credit-card"
                                                wire:click="$dispatch('open-payment-modal', { orderId: {{ $order->id }} })"
                                                x-on:click="$flux.modal('payment-modal').show()">
                                                Paiement
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="pencil-square"
                                                wire:click="$dispatch('open-order-modal', { id: {{ $order->id }} })"
                                                x-on:click="$flux.modal('order-form-modal').show()">
                                                Modifier
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>

                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($orders->hasPages())
                    <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $orders->links() }}
                    </div>
                @endif
            @endif
        </x-card.card-body>
    </flux:card>

    <livewire:pages::orders.modals.order-form-modal />
    <livewire:pages::orders.modals.payment-modal />
    <livewire:pages::clients.modals.client-form-modal />
</div>
