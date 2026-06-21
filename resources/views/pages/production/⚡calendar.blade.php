<?php

use Livewire\Component;
use App\Enums\OrderStatus;
use App\Models\Order;

new #[Title('Calendrier de Production')] class extends Component {
    public string $weekStart = '';

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');
    }

    public function previousWeek(): void
    {
        $this->weekStart = now()->parse($this->weekStart)->subWeek()->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $this->weekStart = now()->parse($this->weekStart)->addWeek()->format('Y-m-d');
    }

    public function currentWeek(): void
    {
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');
    }

    public function getWeekDaysProperty(): array
    {
        $start = now()->parse($this->weekStart);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $days[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->isoFormat('dddd D'),
                'isToday' => $date->isToday(),
            ];
        }
        return $days;
    }

    public function getOrdersByDayProperty(): array
    {
        $start = now()->parse($this->weekStart);
        $end = $start->copy()->endOfWeek();

        $orders = Order::notCancelled()
            ->whereBetween('delivery_due_at', [$start, $end])
            ->with(['levels.recipe', 'client'])
            ->orderBy('delivery_due_at')
            ->get();

        $grouped = [];
        foreach ($orders as $order) {
            $day = $order->delivery_due_at->format('Y-m-d');
            $grouped[$day][] = $order;
        }
        return $grouped;
    }

    public function getStatsProperty(): array
    {
        $weekStart = now()->parse($this->weekStart);
        $weekEnd = $weekStart->copy()->endOfWeek();

        return [
            'totalOrders' => Order::notCancelled()->whereBetween('delivery_due_at', [$weekStart, $weekEnd])->count(),
            'inProduction' => Order::notCancelled()->whereBetween('delivery_due_at', [$weekStart, $weekEnd])
                ->where('status', OrderStatus::EN_PRODUCTION)->count(),
            'confirmed' => Order::notCancelled()->whereBetween('delivery_due_at', [$weekStart, $weekEnd])
                ->whereIn('status', [OrderStatus::CONFIRMÉE, OrderStatus::ACOMPTE_PERÇU])->count(),
            'ready' => Order::notCancelled()->whereBetween('delivery_due_at', [$weekStart, $weekEnd])
                ->whereIn('status', [OrderStatus::PRÊTE, OrderStatus::EN_LIVRAISON])->count(),
        ];
    }

    public function with(): array
    {
        return [
            'weekDays' => $this->weekDays,
            'ordersByDay' => $this->ordersByDay,
            'stats' => $this->stats,
            'currentWeekLabel' => now()->parse($this->weekStart)->isoFormat('D MMMM YYYY')
                . ' — ' . now()->parse($this->weekStart)->endOfWeek()->isoFormat('D MMMM YYYY'),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Calendrier de Production'"
        :subtitle="'Planification des productions en atelier — Semaine du ' . $currentWeekLabel">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Atelier & Production</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Calendrier</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <flux:card class="border border-blue-200/80 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-blue-600 dark:text-blue-400 text-xs font-semibold uppercase tracking-wider">Commandes</flux:text>
                <div class="text-2xl font-black text-blue-700 dark:text-blue-300 mt-1">{{ $stats['totalOrders'] }}</div>
            </x-card.card-body>
        </flux:card>

        <flux:card class="border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-amber-600 dark:text-amber-400 text-xs font-semibold uppercase tracking-wider">À produire</flux:text>
                <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ $stats['confirmed'] }}</div>
            </x-card.card-body>
        </flux:card>

        <flux:card class="border border-indigo-200/80 dark:border-indigo-800/60 bg-indigo-50/50 dark:bg-indigo-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-indigo-600 dark:text-indigo-400 text-xs font-semibold uppercase tracking-wider">En production</flux:text>
                <div class="text-2xl font-black text-indigo-700 dark:text-indigo-300 mt-1">{{ $stats['inProduction'] }}</div>
            </x-card.card-body>
        </flux:card>

        <flux:card class="border border-green-200/80 dark:border-green-800/60 bg-green-50/50 dark:bg-green-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-green-600 dark:text-green-400 text-xs font-semibold uppercase tracking-wider">Prêtes / Livrées</flux:text>
                <div class="text-2xl font-black text-green-700 dark:text-green-300 mt-1">{{ $stats['ready'] }}</div>
            </x-card.card-body>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header title="Planning Hebdomadaire" :subtitle="$currentWeekLabel">
            <x-slot:menu>
                <flux:button size="xs" variant="ghost" wire:click="previousWeek" icon="chevron-left" class="cursor-pointer" />
                <flux:button size="xs" variant="ghost" wire:click="currentWeek" class="cursor-pointer text-xs font-medium">Aujourd'hui</flux:button>
                <flux:button size="xs" variant="ghost" wire:click="nextWeek" icon="chevron-right" class="cursor-pointer" icon-trailing />
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <div class="grid grid-cols-7 border-b border-zinc-200 dark:border-zinc-700">
                @foreach($weekDays as $day)
                    <div class="px-2 py-3 text-center {{ $day['isToday'] ? 'bg-amber-50 dark:bg-amber-950/20' : '' }} border-r border-zinc-200 dark:border-zinc-700 last:border-r-0">
                        <flux:text class="text-xs font-semibold uppercase tracking-wider {{ $day['isToday'] ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                            {{ $day['label'] }}
                        </flux:text>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 min-h-[400px]">
                @foreach($weekDays as $day)
                    <div class="border-r border-zinc-200 dark:border-zinc-700 last:border-r-0 p-1.5 space-y-1.5 {{ $day['isToday'] ? 'bg-amber-50/30 dark:bg-amber-950/10' : 'bg-white dark:bg-zinc-900' }}">
                        @php $dayOrders = $ordersByDay[$day['date']] ?? []; @endphp

                        @forelse($dayOrders as $order)
                            <a href="{{ route('orders.show', $order) }}" wire:navigate class="block">
                                <flux:card class="border-2 border-blue-600/50! shadow-sm! shadow-blue-600/20! cursor-pointer">
                                    <x-card.card-body>
                                        <flux:text class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 truncate">
                                            #{{ $order->reference }}
                                        </flux:text>
                                        <flux:text class="text-[10px] text-zinc-400 mt-0.5 truncate">
                                            {{ $order->client->name }}
                                        </flux:text>
                                        <div class="mt-1">
                                            <flux:badge :variant="$order->status->badgeVariant()" size="xs" class="px-1 py-0.5 text-[10px]">
                                                {{ $order->status->label() }}
                                            </flux:badge>
                                        </div>
                                    </x-card.card-body>
                                </flux:card>
                            </a>
                        @empty
                            @if(!$day['isToday'])
                                <div class="h-full flex items-center justify-center">
                                    <flux:text class="text-[10px] text-zinc-300 dark:text-zinc-600">—</flux:text>
                                </div>
                            @else
                                <div class="h-full flex items-center justify-center">
                                    <flux:text class="text-[10px] text-zinc-400 dark:text-zinc-500">Aucune commande</flux:text>
                                </div>
                            @endif
                        @endforelse
                    </div>
                @endforeach
            </div>
        </x-card.card-body>
    </flux:card>

    @if(count($ordersByDay) > 0)
        <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs">
            <x-card.card-header title="Détail des commandes de la semaine" />
            <x-card.card-body class="p-0">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Réf.</flux:table.column>
                        <flux:table.column>Client</flux:table.column>
                        <flux:table.column>Gâteau</flux:table.column>
                        <flux:table.column>Étages</flux:table.column>
                        <flux:table.column class="text-center">Livraison</flux:table.column>
                        <flux:table.column>Statut</flux:table.column>
                        <flux:table.column class="text-right">Montant</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach(collect($ordersByDay)->flatten(1) as $order)
                            <flux:table.row :key="$order->id" class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                wire:click="redirect({{ $order->id }})" onclick="window.location='{{ route('orders.show', $order) }}'">
                                <flux:table.cell>
                                    <span class="font-mono text-xs font-medium">#{{ $order->reference }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm">{{ $order->client->name }}</flux:table.cell>
                                <flux:table.cell class="text-sm">{{ $order->cake_type ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-center">{{ $order->tiers_count }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-center">{{ $order->delivery_due_at?->format('d/m') ?: '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :variant="$order->status->badgeVariant()" size="sm">{{ $order->status->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-right text-sm font-medium">{{ number_format($order->total_amount, 0, ',', ' ') }} F</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-card.card-body>
        </flux:card>
    @endif
</div>
