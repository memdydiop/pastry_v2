<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Traits\WithSorting;

new #[Title('Factures & Reçus')] class extends Component {
    use WithPagination;
    use WithSorting;

    public $search = '';
    public $statusFilter = '';
    public $perPage = PER_PAGE;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function getStatusesProperty(): array
    {
        return [
            OrderStatus::CONFIRMÉE,
            OrderStatus::EN_PRODUCTION,
            OrderStatus::PRÊTE,
            OrderStatus::EN_LIVRAISON,
            OrderStatus::LIVRÉE,
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Order::notCancelled()
            ->whereIn('status', [
                OrderStatus::CONFIRMÉE,
                OrderStatus::EN_PRODUCTION,
                OrderStatus::PRÊTE,
                OrderStatus::EN_LIVRAISON,
                OrderStatus::LIVRÉE,
            ]);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('reference', 'like', '%' . $this->search . '%')
                  ->orWhere('client_name', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        $orders = $query
            ->with('transactions')
            ->when($this->sortBy, fn($q) => $q->orderBy($this->sortBy, $this->sortDirection), fn($q) => $q->orderBy('delivery_due_at', 'desc'))
            ->paginate($this->perPage);

        return [
            'orders' => $orders,
            'isFiltered' => !empty($this->search) || !empty($this->statusFilter),
            'totalInvoiced' => Order::notCancelled()->whereIn('status', [
                OrderStatus::CONFIRMÉE, OrderStatus::LIVRÉE,
            ])->sum('total_amount'),
            'totalPending' => Order::notCancelled()->whereIn('status', [
                OrderStatus::EN_ATTENTE, OrderStatus::ACOMPTE_PERÇU,
            ])->sum('total_amount'),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Factures & Reçus'"
        :subtitle="'Consultez et imprimez les reçus clients et factures.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Finance</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Factures</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-emerald-600 dark:text-emerald-400 text-xs font-semibold uppercase tracking-wider">Total facturé</flux:text>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">{{ number_format($totalInvoiced, 0, ',', ' ') }} F</div>
            </x-card.card-body>
        </flux:card>

        <flux:card class="border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-amber-600 dark:text-amber-400 text-xs font-semibold uppercase tracking-wider">En attente de validation</flux:text>
                <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ number_format($totalPending, 0, ',', ' ') }} F</div>
            </x-card.card-body>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header title="Commandes facturables" subtitle="Commandes confirmées, produites et livrées">
            <x-slot:menu>
                <flux:menu.item icon="arrow-down-tray" class="cursor-pointer" x-on:click="alert('Export CSV disponible prochainement.')">
                    Exporter
                </flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Réf. commande ou client..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
                <flux:select wire:model.live="statusFilter" placeholder="Tous les statuts" class="text-sm">
                    @foreach($this->statuses as $s)
                        <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </x-card.table-filters>

            @if($orders->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 flex items-center justify-center text-xl mx-auto shadow-xs">🧾</div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucune facture disponible</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400">Les factures apparaîtront lorsque les commandes seront confirmées ou livrées.</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'reference'" :direction="$sortBy === 'reference' ? $sortDirection : null" wire:click="sort('reference')">RÉf.</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'client_name'" :direction="$sortBy === 'client_name' ? $sortDirection : null" wire:click="sort('client_name')">Client</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortBy === 'created_at' ? $sortDirection : null" wire:click="sort('created_at')">Date commande</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'delivery_due_at'" :direction="$sortBy === 'delivery_due_at' ? $sortDirection : null" wire:click="sort('delivery_due_at')">Livraison</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortBy === 'status' ? $sortDirection : null" wire:click="sort('status')">Statut</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'total_amount'" :direction="$sortBy === 'total_amount' ? $sortDirection : null" wire:click="sort('total_amount')" class="text-right">Montant</flux:table.column>
                        <flux:table.column class="text-right">Payé</flux:table.column>
                        <flux:table.column class="text-right">Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($orders as $order)
                            <flux:table.row :key="$order->id">
                                <flux:table.cell>
                                    <span class="font-mono text-xs font-medium">#{{ $order->reference }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm">{{ $order->client->name }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $order->created_at->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $order->delivery_due_at?->format('d/m/Y') ?: '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :variant="$order->status->badgeVariant()" size="sm">{{ $order->status->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-right text-sm font-medium">{{ number_format($order->total_amount, 0, ',', ' ') }} F</flux:table.cell>
                                <flux:table.cell class="text-right text-sm {{ $order->total_paid >= $order->total_amount ? 'text-emerald-600' : 'text-amber-600' }}">
                                    {{ number_format($order->total_paid, 0, ',', ' ') }} F
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" title="Actions" />
                                        <flux:menu>
                                            <flux:menu.item icon="arrow-down-tray" :href="route('invoices.download', $order)">
                                                Télécharger PDF
                                            </flux:menu.item>
                                            <flux:menu.item icon="eye" :href="route('orders.show', $order)" wire:navigate>
                                                Voir la commande
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
</div>
