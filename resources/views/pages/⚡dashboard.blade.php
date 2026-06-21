<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\Client;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Ingredient;
use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\CarbonInterface;

new #[Title('Tableau de Bord')] class extends Component
{
    private const DEFAULT_LEVEL_COST = 5000;

    public string $period = '1M';

    public function setPeriod(string $period): void
    {
        if (in_array($period, ['1M', '6M', '1Y', 'ALL'])) {
            $this->period = $period;
        }
    }

    private function dateThreshold(): ?CarbonInterface
    {
        return match ($this->period) {
            '1M' => now()->subDays(30),
            '6M' => now()->subDays(180),
            '1Y' => now()->subDays(365),
            default => null,
        };
    }

    private function chartDaysCount(): int
    {
        $days = match ($this->period) {
            '1M' => 30,
            '6M' => 180,
            '1Y' => 365,
            default => 365,
        };

        if ($this->period === 'ALL') {
            $firstOrder = Order::notCancelled()->orderBy('created_at')->first();
            $days = $firstOrder ? max(30, now()->diffInDays($firstOrder->created_at) + 1) : 365;
        }

        return $days;
    }

    public function exportCsv()
    {
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=rapport_dashboard_" . $this->period . "_" . now()->format('Ymd') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $dateThreshold = $this->dateThreshold();

        $txQuery = Transaction::notCancelled()->orderBy('paid_at');
        if ($dateThreshold) {
            $txQuery->where('paid_at', '>=', $dateThreshold);
        }
        $transactions = $txQuery->get();

        $orderQuery = Order::notCancelled()->orderBy('created_at');
        if ($dateThreshold) {
            $orderQuery->where('created_at', '>=', $dateThreshold);
        }
        $orders = $orderQuery->get();

        $callback = function() use ($transactions, $orders) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Rapport d\'Activité Pâtisserie']);
            fputcsv($file, ['Période', $this->period]);
            fputcsv($file, ['Généré le', now()->format('d/m/Y H:i')]);
            fputcsv($file, []);

            fputcsv($file, ['Type', 'Référence', 'Date', 'Client', 'Détails / Notes', 'Montant (FCFA)']);

            foreach ($orders as $o) {
                fputcsv($file, [
                    'Commande',
                    $o->reference,
                    $o->created_at->format('d/m/Y'),
                    $o->client_name,
                    $o->cake_type . ' (' . $o->tiers_count . ' étages)',
                    $o->total_amount
                ]);
            }

            foreach ($transactions as $t) {
                fputcsv($file, [
                    $t->type === TransactionType::PAYMENT ? 'Paiement' : 'Remboursement',
                    $t->reference,
                    $t->paid_at->format('d/m/Y'),
                    $t->order?->client_name ?? 'N/A',
                    $t->notes,
                    $t->amount
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function with(): array
    {
        $dateThreshold = $this->dateThreshold();
        $chartDaysCount = $this->chartDaysCount();

        $chartTransactions = Transaction::notCancelled()
            ->where('paid_at', '>=', now()->subDays($chartDaysCount))
            ->get();

        $chartOrders = Order::notCancelled()
            ->where('created_at', '>=', now()->subDays($chartDaysCount))
            ->get();

        $periodOrdersQuery = Order::notCancelled();
        if ($dateThreshold) {
            $periodOrdersQuery->where('created_at', '>=', $dateThreshold);
        }

        $totalRevenue = (clone $periodOrdersQuery)->sum('total_amount');
        $totalRefunds = Transaction::notCancelled()
            ->where('type', TransactionType::REFUND)
            ->when($dateThreshold, fn($q) => $q->where('paid_at', '>=', $dateThreshold))
            ->sum('amount');
        $netRevenue = $totalRevenue - $totalRefunds;

        if ($dateThreshold) {
            $totalOrders = $chartOrders->count();
            $pendingOrders = $chartOrders->filter(fn($o) => !in_array($o->status->value, [OrderStatus::LIVRÉE->value, OrderStatus::ANNULÉE->value]))->count();
            $recentOrders = $chartOrders->sortByDesc('created_at')->take(5)->map(fn($o) => [
                'id' => $o->id,
                'reference' => $o->reference,
                'client_name' => $o->client_name,
                'cake_type' => $o->cake_type ?? 'Gâteau sur mesure',
                'total' => $o->total_amount,
                'status' => $o->status->value,
                'delivery_due_at' => $o->delivery_due_at,
            ])->values();
            $statusDistribution = $chartOrders->groupBy(fn($o) => $o->status->value)
                ->map(fn($group, $status) => ['status' => $status, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values()
                ->toArray();
            $topCakeTypes = $chartOrders->whereNotNull('cake_type')
                ->groupBy('cake_type')
                ->map(fn($group, $type) => ['cake_type' => $type, 'count' => $group->count()])
                ->sortByDesc('count')
                ->take(5)
                ->values()
                ->toArray();
        } else {
            $totalOrders = (clone $periodOrdersQuery)->count();
            $pendingOrders = (clone $periodOrdersQuery)->whereNotIn('status', [OrderStatus::LIVRÉE->value, OrderStatus::ANNULÉE->value])->count();
            $recentOrders = (clone $periodOrdersQuery)->with('client')->latest()->take(5)->get()->map(fn($o) => [
                'id' => $o->id,
                'reference' => $o->reference,
                'client_name' => $o->client_name,
                'cake_type' => $o->cake_type ?? 'Gâteau sur mesure',
                'total' => $o->total_amount,
                'status' => $o->status->value,
                'delivery_due_at' => $o->delivery_due_at,
            ]);
            $statusDistribution = (clone $periodOrdersQuery)->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->orderByDesc('count')
                ->get()
                ->toArray();
            $topCakeTypes = (clone $periodOrdersQuery)->select('cake_type', DB::raw('count(*) as count'))
                ->whereNotNull('cake_type')
                ->groupBy('cake_type')
                ->orderByDesc('count')
                ->take(5)
                ->get()
                ->toArray();
        }

        $totalClients = Client::count();

        $marginKey = 'dashboard_margin_' . $this->period;
        $marginData = Cache::remember($marginKey, 60, fn() => $this->computeMargin($periodOrdersQuery));

        $biscuitQuery = DB::table('order_levels')
            ->join('orders', 'order_levels.order_id', '=', 'orders.id')
            ->whereNull('orders.cancelled_at')
            ->select('order_levels.flavor_biscuit as flavor', DB::raw('count(*) as count'))
            ->whereNotNull('order_levels.flavor_biscuit')
            ->where('order_levels.flavor_biscuit', '<>', '');

        $creamQuery = DB::table('order_levels')
            ->join('orders', 'order_levels.order_id', '=', 'orders.id')
            ->whereNull('orders.cancelled_at')
            ->select('order_levels.flavor_cream as flavor', DB::raw('count(*) as count'))
            ->whereNotNull('order_levels.flavor_cream')
            ->where('order_levels.flavor_cream', '<>', '');

        if ($dateThreshold) {
            $biscuitQuery->where('orders.created_at', '>=', $dateThreshold);
            $creamQuery->where('orders.created_at', '>=', $dateThreshold);
        }

        $topBiscuits = $biscuitQuery->groupBy('order_levels.flavor_biscuit')
            ->orderByDesc('count')
            ->take(3)
            ->get()
            ->toArray();

        $topCreams = $creamQuery->groupBy('order_levels.flavor_cream')
            ->orderByDesc('count')
            ->take(3)
            ->get()
            ->toArray();

        $criticalStocks = Ingredient::where('is_critical', true)
            ->whereColumn('stock_quantity', '<=', 'alert_threshold')
            ->get();

        $chartData = $this->buildChartData($chartTransactions, $chartOrders, $chartDaysCount);

        return [
            'totalRevenue' => $totalRevenue,
            'totalRefunds' => $totalRefunds,
            'netRevenue' => $netRevenue,
            'totalOrders' => $totalOrders,
            'totalClients' => $totalClients,
            'pendingOrders' => $pendingOrders,
            'recentOrders' => $recentOrders,
            'statusDistribution' => $statusDistribution,
            'topCakeTypes' => $topCakeTypes,
            'chartData' => $chartData,
            'totalMargin' => $marginData['totalMargin'],
            'marginPercentage' => $marginData['marginPercentage'],
            'topBiscuits' => $topBiscuits,
            'topCreams' => $topCreams,
            'criticalStocks' => $criticalStocks,
        ];
    }

    private function computeMargin(Builder $ordersQuery): array
    {
        $periodOrders = (clone $ordersQuery)
            ->select('id', 'total_amount')
            ->with('levels:order_id,recipe_id', 'levels.recipe:id', 'levels.recipe.recipeIngredients:recipe_id,ingredient_id,quantity', 'levels.recipe.recipeIngredients.ingredient:id,unit_price')
            ->get();
        $totalPeriodAmount = 0;
        $totalPeriodCost = 0;

        foreach ($periodOrders as $order) {
            $orderCost = 0;
            foreach ($order->levels as $level) {
                if ($level->recipe_id && $level->recipe) {
                    $levelCost = 0;
                    foreach ($level->recipe->recipeIngredients as $ri) {
                        $price = $ri->ingredient?->unit_price ?? 0;
                        $levelCost += $ri->quantity * $price;
                    }
                    $orderCost += $levelCost;
                } else {
                    $orderCost += self::DEFAULT_LEVEL_COST;
                }
            }
            $totalPeriodAmount += $order->total_amount;
            $totalPeriodCost += $orderCost;
        }

        $totalMargin = $totalPeriodAmount - $totalPeriodCost;
        $marginPercentage = $totalPeriodAmount > 0 ? ($totalMargin / $totalPeriodAmount) * 100 : 0;

        return [
            'totalMargin' => $totalMargin,
            'marginPercentage' => $marginPercentage,
        ];
    }

    private function buildChartData($transactions, $orders, int $chartDaysCount): array
    {
        $chartData = [];

        if ($this->period === '1M') {
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dayRevenue = $orders->filter(fn($o) => $o->created_at->isSameDay($date))->sum('total_amount');
                $dayRefunds = $transactions->filter(fn($t) => $t->paid_at->isSameDay($date) && $t->type === TransactionType::REFUND)->sum('amount');
                $dayOrders = $orders->filter(fn($o) => $o->created_at->isSameDay($date))->count();

                $chartData[] = [
                    'date' => $date->format('Y-m-d'),
                    'label' => $date->translatedFormat('d M'),
                    'revenue' => $dayRevenue,
                    'refunds' => $dayRefunds,
                    'orders' => (int) $dayOrders,
                ];
            }
        } elseif ($this->period === '6M') {
            for ($i = 25; $i >= 0; $i--) {
                $startOfWeek = now()->subWeeks($i)->startOfWeek();
                $endOfWeek = now()->subWeeks($i)->endOfWeek();

                $weekRevenue = $orders->filter(fn($o) => $o->created_at->between($startOfWeek, $endOfWeek))->sum('total_amount');
                $weekRefunds = $transactions->filter(fn($t) => $t->paid_at->between($startOfWeek, $endOfWeek) && $t->type === TransactionType::REFUND)->sum('amount');
                $weekOrders = $orders->filter(fn($o) => $o->created_at->between($startOfWeek, $endOfWeek))->count();

                $chartData[] = [
                    'date' => $startOfWeek->format('Y-m-d'),
                    'label' => $startOfWeek->translatedFormat('\S\e\m W'),
                    'revenue' => $weekRevenue,
                    'refunds' => $weekRefunds,
                    'orders' => (int) $weekOrders,
                ];
            }
        } else {
            $monthsCount = $this->period === 'ALL' ? (int) ($chartDaysCount / 30) : 12;
            $monthsCount = max(6, min(24, $monthsCount));

            for ($i = $monthsCount - 1; $i >= 0; $i--) {
                $startOfMonth = now()->subMonths($i)->startOfMonth();
                $endOfMonth = now()->subMonths($i)->endOfMonth();

                $monthRevenue = $orders->filter(fn($o) => $o->created_at->between($startOfMonth, $endOfMonth))->sum('total_amount');
                $monthRefunds = $transactions->filter(fn($t) => $t->paid_at->between($startOfMonth, $endOfMonth) && $t->type === TransactionType::REFUND)->sum('amount');
                $monthOrders = $orders->filter(fn($o) => $o->created_at->between($startOfMonth, $endOfMonth))->count();

                $chartData[] = [
                    'date' => $startOfMonth->format('Y-m-d'),
                    'label' => $startOfMonth->translatedFormat('M Y'),
                    'revenue' => $monthRevenue,
                    'refunds' => $monthRefunds,
                    'orders' => (int) $monthOrders,
                ];
            }
        }

        return $chartData;
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Tableau de Bord'"
        :subtitle="'Suivi de l\'activité de la pâtisserie en temps réel.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Dashboard</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Aperçu</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <!-- Cards Statistiques -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Chiffre d'affaires -->
        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20 shadow-xs">
            <x-card.card-body class="p-5">
                <flux:text class="text-emerald-600 dark:text-emerald-400 text-sm font-medium uppercase tracking-wider">Chiffre d'Affaires</flux:text>
                <flux:heading size="xl" class="mt-1 font-bold text-zinc-900 dark:text-white">
                    {{ number_format($totalRevenue, 0, ',', ' ') }} FCFA
                </flux:heading>
                <flux:text size="sm" class="mt-1 text-emerald-500">Remboursements : −{{ number_format($totalRefunds, 0, ',', ' ') }} FCFA</flux:text>
            </x-card.card-body>
        </flux:card>

        <!-- Marge Réelle Estimée -->
        <flux:card class="border border-indigo-200/80 dark:border-indigo-800/60 bg-indigo-50/50 dark:bg-indigo-950/20 shadow-xs">
            <x-card.card-body class="p-5">
                <flux:text class="text-indigo-600 dark:text-indigo-400 text-sm font-medium uppercase tracking-wider">Marge Réelle Est.</flux:text>
                <flux:heading size="xl" class="mt-1 font-bold text-zinc-900 dark:text-white">
                    {{ number_format($totalMargin, 0, ',', ' ') }} FCFA
                </flux:heading>
                <flux:text size="sm" class="mt-1 text-indigo-500">Taux moyen : {{ number_format($marginPercentage, 1, ',', ' ') }}%</flux:text>
            </x-card.card-body>
        </flux:card>

        <!-- Commandes -->
        <flux:card class="border border-blue-200/80 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20 shadow-xs">
            <x-card.card-body class="p-5">
                <flux:text class="text-blue-600 dark:text-blue-400 text-sm font-medium uppercase tracking-wider">Commandes ({{ $period }})</flux:text>
                <flux:heading size="xl" class="mt-1 font-bold text-zinc-900 dark:text-white">
                    {{ number_format($totalOrders, 0, ',', ' ') }}
                </flux:heading>
                <flux:text size="sm" class="mt-1 text-blue-500">{{ $pendingOrders }} en cours</flux:text>
            </x-card.card-body>
        </flux:card>

        <!-- Clients -->
        <flux:card class="border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20 shadow-xs">
            <x-card.card-body class="p-5">
                <flux:text class="text-amber-600 dark:text-amber-400 text-sm font-medium uppercase tracking-wider">Clients</flux:text>
                <flux:heading size="xl" class="mt-1 font-bold text-zinc-900 dark:text-white">
                    {{ number_format($totalClients, 0, ',', ' ') }}
                </flux:heading>
                <flux:text size="sm" class="mt-1 text-amber-500">Fiches clients actives</flux:text>
            </x-card.card-body>
        </flux:card>
    </div>

    <!-- Graphiques & Alertes Stock -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <!-- Graphique principal -->
        <flux:card class="col-span-1 md:col-span-8 border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-sm">
            <div class="border-b-0 flex items-center justify-between px-6 pt-5 pb-0">
                <h4 class="text-sm font-semibold text-zinc-800 dark:text-white uppercase tracking-wider">Revenus & Activité</h4>
                <div class="flex items-center gap-2">
                    <flux:button size="xs" icon="document-arrow-down" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" class="cursor-pointer">
                        <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                        <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                            <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Export...
                        </span>
                    </flux:button>
                    <div class="flex gap-1 bg-zinc-100 dark:bg-zinc-800 p-0.5 rounded-lg">
                        <button type="button" wire:click="setPeriod('ALL')" wire:loading.attr="disabled" wire:target="setPeriod" class="text-xs px-2.5 py-1 rounded-md transition-colors {{ $period === 'ALL' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-xs font-semibold' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200' }}">ALL</button>
                        <button type="button" wire:click="setPeriod('1M')" wire:loading.attr="disabled" wire:target="setPeriod" class="text-xs px-2.5 py-1 rounded-md transition-colors {{ $period === '1M' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-xs font-semibold' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200' }}">1M</button>
                        <button type="button" wire:click="setPeriod('6M')" wire:loading.attr="disabled" wire:target="setPeriod" class="text-xs px-2.5 py-1 rounded-md transition-colors {{ $period === '6M' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-xs font-semibold' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200' }}">6M</button>
                        <button type="button" wire:click="setPeriod('1Y')" wire:loading.attr="disabled" wire:target="setPeriod" class="text-xs px-2.5 py-1 rounded-md transition-colors {{ $period === '1Y' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-xs font-semibold' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200' }}">1Y</button>
                    </div>
                </div>
            </div>

            <!-- Totaux sur la période -->
            <div class="p-0 border-0 bg-zinc-50 dark:bg-zinc-800/50 mx-6 mt-4 rounded-lg">
                <div class="grid grid-cols-4 text-center">
                    <div class="p-3 border-r border-dashed border-zinc-200 dark:border-zinc-700">
                        <h5 class="mb-0 text-sm font-bold text-zinc-900 dark:text-white">{{ number_format($totalOrders, 0, ',', ' ') }}</h5>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Commandes</p>
                    </div>
                    <div class="p-3 border-r border-dashed border-zinc-200 dark:border-zinc-700">
                        <h5 class="mb-0 text-sm font-bold text-zinc-900 dark:text-white">{{ number_format($netRevenue, 0, ',', ' ') }} FCFA</h5>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Revenus Nets</p>
                    </div>
                    <div class="p-3 border-r border-dashed border-zinc-200 dark:border-zinc-700">
                        <h5 class="mb-0 text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($totalMargin, 0, ',', ' ') }} FCFA</h5>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Marge Est.</p>
                    </div>
                    <div class="p-3">
                        <h5 class="mb-0 text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            @php $ratio = $totalOrders > 0 ? round(($pendingOrders / $totalOrders) * 100, 1) : 0; @endphp
                            {{ $ratio }}%
                        </h5>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">En cours</p>
                    </div>
                </div>
            </div>

            <x-card.card-body class="p-6">
                <script type="application/json" id="revenueChartData">@json($chartData)</script>
                <div wire:ignore id="revenueChart" class="apex-charts" dir="ltr"></div>
            </x-card.card-body>
        </flux:card>

        <!-- Sidebar droite : Stocks Critiques & Statuts -->
        <div class="col-span-1 md:col-span-4 flex flex-col gap-4">
            <!-- Alertes Stocks Critiques -->
            @if($criticalStocks->isNotEmpty())
                <flux:card class="border border-rose-200/80 dark:border-rose-800/60 bg-rose-50/50 dark:bg-rose-950/20 p-5 shadow-xs">
                    <x-card.card-body>
                    <div class="flex items-center gap-2 text-rose-700 dark:text-rose-400 font-semibold mb-3">
                        <flux:icon.exclamation-triangle class="size-5" />
                        <h4 class="text-sm uppercase tracking-wider">Stocks Critiques !</h4>
                    </div>
                    <div class="space-y-3">
                        @foreach($criticalStocks as $stock)
                            <div class="flex items-center justify-between text-sm bg-white dark:bg-zinc-900/60 p-2.5 rounded-lg border border-rose-100 dark:border-rose-900/40">
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $stock->name }}</span>
                                <span class="text-rose-600 dark:text-rose-400 font-bold">{{ number_format($stock->stock_quantity, 1) }} / {{ number_format($stock->alert_threshold, 1) }} {{ $stock->unit }}</span>
                            </div>
                        @endforeach
                    </div>
                    </x-card.card-body>
                </flux:card>
            @else
                <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20 p-5 shadow-xs flex items-center justify-center py-6 text-center">
                    <x-card.card-body>
                    <div class="flex flex-col items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                        <flux:icon.check-circle class="size-6" />
                        <h4 class="text-sm font-semibold uppercase tracking-wider">Stocks d'alerte OK</h4>
                        <p class="text-xs text-emerald-600 dark:text-emerald-500">Toutes les matières premières critiques sont approvisionnées.</p>
                    </div>
                    </x-card.card-body>
                </flux:card>
            @endif

            <!-- Répartition par Statut -->
            <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-sm flex-1">
                <x-card.card-header title="Répartition par Statut" subtitle="Commandes par état d'avancement" />
                <x-card.card-body class="p-5 space-y-3">
                    @forelse($statusDistribution as $s)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ App\Enums\OrderStatus::tryFrom($s['status'])?->label() ?? $s['status'] }}</span>
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ $s['count'] }}</span>
                        </div>
                    @empty
                        <flux:text size="sm" class="text-zinc-400">Aucune commande pour le moment.</flux:text>
                    @endforelse
                </x-card.card-body>
            </flux:card>
        </div>
    </div>

    <!-- Row 2 : Dernières commandes & Palmarès -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <!-- Dernières Commandes -->
        <flux:card class="col-span-1 md:col-span-8 border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-sm">
            <x-card.card-header title="Dernières Commandes" subtitle="Les 5 dernières commandes enregistrées">
                <x-slot:menu>
                    <flux:menu.item icon="arrow-right" :href="route('orders.index')">Voir toutes les commandes</flux:menu.item>
                </x-slot:menu>
            </x-card.card-header>

            <x-card.card-body class="p-0">
                @if($recentOrders->isEmpty())
                    <div class="text-center py-12 text-zinc-400">Aucune commande enregistrée.</div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Référence</flux:table.column>
                            <flux:table.column>Client</flux:table.column>
                            <flux:table.column>Gâteau</flux:table.column>
                            <flux:table.column>Statut</flux:table.column>
                            <flux:table.column class="text-right">Montant</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach($recentOrders as $o)
                                <flux:table.row>
                                    <flux:table.cell class="font-mono text-xs font-semibold">{{ $o['reference'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $o['client_name'] }}</flux:table.cell>
                                    <flux:table.cell class="text-zinc-500 text-sm">{{ $o['cake_type'] }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :variant="App\Enums\OrderStatus::tryFrom($o['status'])?->badgeVariant() ?? 'neutral'" size="sm">
                                            {{ App\Enums\OrderStatus::tryFrom($o['status'])?->label() ?? $o['status'] }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right font-bold">{{ number_format($o['total'], 0, ',', ' ') }} FCFA</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </x-card.card-body>
        </flux:card>

        <!-- Sidebar droite 2 : Palmarès des Parfums & Top Gâteaux -->
        <div class="col-span-1 md:col-span-4 flex flex-col gap-4">
            <!-- Palmarès des Parfums -->
            <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-sm">
                <x-card.card-header title="Top Parfums" subtitle="Biscuits et crèmes populaires" />
                <x-card.card-body class="p-5 space-y-4">
                    <div>
                        <h5 class="text-xs font-semibold uppercase text-zinc-400 tracking-wider mb-2">Biscuits</h5>
                        <div class="space-y-2">
                            @forelse($topBiscuits as $b)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-700 dark:text-zinc-300">{{ $b->flavor }}</span>
                                    <span class="font-bold text-zinc-900 dark:text-white">{{ $b->count }}</span>
                                </div>
                            @empty
                                <span class="text-xs text-zinc-400">Aucune donnée</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="border-t border-zinc-100 dark:border-zinc-800/80 pt-3">
                        <h5 class="text-xs font-semibold uppercase text-zinc-400 tracking-wider mb-2">Crèmes</h5>
                        <div class="space-y-2">
                            @forelse($topCreams as $c)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-700 dark:text-zinc-300">{{ $c->flavor }}</span>
                                    <span class="font-bold text-zinc-900 dark:text-white">{{ $c->count }}</span>
                                </div>
                            @empty
                                <span class="text-xs text-zinc-400">Aucune donnée</span>
                            @endforelse
                        </div>
                    </div>
                </x-card.card-body>
            </flux:card>

            <!-- Top Gâteaux -->
            <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-sm flex-1">
                <x-card.card-header title="Top Gâteaux" subtitle="Les types de gâteaux les plus commandés" />
                <x-card.card-body class="p-5 space-y-3">
                    @forelse($topCakeTypes as $c)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $c['cake_type'] ?? 'Non spécifié' }}</span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $c['count'] }}</span>
                        </div>
                    @empty
                        <flux:text size="sm" class="text-zinc-400">Aucune donnée disponible.</flux:text>
                    @endforelse
                </x-card.card-body>
            </flux:card>
        </div>
    </div>
</div>
