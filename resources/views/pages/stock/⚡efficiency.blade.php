<?php

use Livewire\Component;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

new #[Title('Rapport d\'Efficacité')] class extends Component {
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 20;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function getTheoreticalConsumptionProperty()
    {
        $orders = Order::notCancelled()
            ->whereIn('status', [
                OrderStatus::CONFIRMÉE,
                OrderStatus::EN_PRODUCTION,
                OrderStatus::PRÊTE,
                OrderStatus::EN_LIVRAISON,
                OrderStatus::LIVRÉE,
            ])
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->with('levels.recipe.recipeIngredients.ingredient')
            ->get();

        $theoretical = collect();

        foreach ($orders as $order) {
            foreach ($order->levels as $level) {
                if (!$level->recipe) {
                    continue;
                }
                foreach ($level->recipe->recipeIngredients as $ri) {
                    $key = $ri->ingredient_id;
                    $theoretical->put($key, [
                        'id' => $ri->ingredient_id,
                        'name' => $ri->ingredient->name ?? '?',
                        'unit' => $ri->ingredient->unit ?? '',
                        'qty' => ($theoretical->get($key)['qty'] ?? 0) + $ri->quantity,
                    ]);
                }
            }
        }

        return $theoretical;
    }

    public function getRealConsumptionProperty()
    {
        return InventoryMovement::where('type', InventoryMovementType::OUT)
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->select('ingredient_id', DB::raw('SUM(quantity) as total_qty'))
            ->with('ingredient')
            ->groupBy('ingredient_id')
            ->get()
            ->keyBy('ingredient_id');
    }

    public function getLossesProperty()
    {
        return InventoryMovement::where('type', InventoryMovementType::LOSS)
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->select('ingredient_id', DB::raw('SUM(quantity) as total_qty'))
            ->with('ingredient')
            ->groupBy('ingredient_id')
            ->get()
            ->keyBy('ingredient_id');
    }

    public function getEfficiencyDataProperty()
    {
        $theoretical = $this->theoreticalConsumption;
        $real = $this->realConsumption;
        $losses = $this->losses;
        $allIngredientIds = $theoretical->pluck('id')->merge($real->keys())->merge($losses->keys())->unique();

        return $allIngredientIds->map(function ($id) use ($theoretical, $real, $losses) {
            $t = $theoretical->get($id);
            $r = $real->get($id);
            $l = $losses->get($id);

            $name = $t['name'] ?? ($r?->ingredient?->name ?? $l?->ingredient?->name ?? '?');
            $unit = $t['unit'] ?? ($r?->ingredient?->unit ?? $l?->ingredient?->unit ?? '');
            $theoQty = $t['qty'] ?? 0;
            $realQty = $r->total_qty ?? 0;
            $lossQty = $l->total_qty ?? 0;
            $efficiency = $theoQty > 0 ? round(($theoQty / max($realQty, 0.001)) * 100, 1) : null;

            return [
                'id' => $id,
                'name' => $name,
                'unit' => $unit,
                'theoretical' => $theoQty,
                'real' => $realQty,
                'losses' => $lossQty,
                'difference' => $realQty - $theoQty,
                'efficiency' => $efficiency,
                'cost' => ($realQty - $theoQty) * ($r?->ingredient?->unit_price ?? 0),
            ];
        })->sortByDesc('difference')->values();
    }

    public function getGlobalEfficiencyProperty()
    {
        $data = $this->efficiencyData;
        $totalTheo = $data->sum('theoretical');
        $totalReal = $data->sum('real');
        return $totalTheo > 0 ? round(($totalTheo / max($totalReal, 0.001)) * 100, 1) : null;
    }

    public function getTotalCostProperty()
    {
        return $this->efficiencyData->sum('cost');
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Rapport d\'Efficacité Matières'"
        :subtitle="'Compare la consommation théorique (via les recettes) à la consommation réelle et aux pertes.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Atelier & Production</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Stocks</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Efficacité</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-emerald-600 dark:text-emerald-400 font-semibold uppercase tracking-wider">Efficacité globale</flux:text>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">
                    @if($this->globalEfficiency !== null)
                        {{ $this->globalEfficiency }}%
                    @else
                        —
                    @endif
                </div>
            </div>
        </flux:card>

        <flux:card class="border border-blue-200/80 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-blue-600 dark:text-blue-400 font-semibold uppercase tracking-wider">Consommation théorique</flux:text>
                <div class="text-2xl font-black text-blue-700 dark:text-blue-300 mt-1">{{ number_format($this->efficiencyData->sum('theoretical'), 1, ',', ' ') }}</div>
            </div>
        </flux:card>

        <flux:card class="border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-amber-600 dark:text-amber-400 font-semibold uppercase tracking-wider">Consommation réelle</flux:text>
                <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ number_format($this->efficiencyData->sum('real'), 1, ',', ' ') }}</div>
            </div>
        </flux:card>

        <flux:card class="border border-rose-200/80 dark:border-rose-800/60 bg-rose-50/50 dark:bg-rose-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-rose-600 dark:text-rose-400 font-semibold uppercase tracking-wider">Surcoût estimé</flux:text>
                <div class="text-2xl font-black text-rose-700 dark:text-rose-300 mt-1">
                    {{ number_format($this->totalCost, 0, ',', ' ') }} <span class="text-sm font-normal">FCFA</span>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header :title="'Détail par ingrédient'" :subtitle="'Période du ' . \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') . ' au ' . \Carbon\Carbon::parse($dateTo)->format('d/m/Y')">
            <x-slot:menu>
                <flux:menu.item icon="arrow-path" wire:click="$refresh" class="cursor-pointer">Actualiser</flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <div class="flex flex-wrap items-center gap-3 p-4 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                <flux:input type="date" wire:model.live="dateFrom" size="sm" class="w-36" label="Du" />
                <flux:input type="date" wire:model.live="dateTo" size="sm" class="w-36" label="Au" />
            </div>

            @if($this->efficiencyData->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 flex items-center justify-center text-xl mx-auto shadow-xs">
                        📊
                    </div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucune donnée</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">Aucune commande ou consommation sur cette période.</flux:text>
                </div>
            @else
                <div class="overflow-x-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Ingrédient</flux:table.column>
                            <flux:table.column class="text-right">Théorique</flux:table.column>
                            <flux:table.column class="text-right">Réelle</flux:table.column>
                            <flux:table.column class="text-right">Pertes</flux:table.column>
                            <flux:table.column class="text-right">Écart</flux:table.column>
                            <flux:table.column class="text-right">Efficacité</flux:table.column>
                            <flux:table.column class="text-right">Surcoût</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($this->efficiencyData as $item)
                                <flux:table.row :key="$item['id']" class="{{ $item['efficiency'] !== null && $item['efficiency'] < 80 ? 'bg-rose-50/50 dark:bg-rose-950/10' : '' }}">
                                    <flux:table.cell>
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $item['name'] }}</span>
                                        <span class="text-xs text-zinc-400 ml-1">({{ $item['unit'] }})</span>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right">{{ number_format($item['theoretical'], 2, ',', ' ') }}</flux:table.cell>
                                    <flux:table.cell class="text-right">{{ number_format($item['real'], 2, ',', ' ') }}</flux:table.cell>
                                    <flux:table.cell class="text-right {{ $item['losses'] > 0 ? 'text-rose-600' : 'text-zinc-500' }}">
                                        {{ number_format($item['losses'], 2, ',', ' ') }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right font-bold {{ $item['difference'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        {{ $item['difference'] > 0 ? '+' : '' }}{{ number_format($item['difference'], 2, ',', ' ') }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        @if($item['efficiency'] !== null)
                                            <flux:badge size="sm" variant="{{ $item['efficiency'] >= 90 ? 'success' : ($item['efficiency'] >= 70 ? 'warning' : 'danger') }}">
                                                {{ $item['efficiency'] }}%
                                            </flux:badge>
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right font-medium text-zinc-500">
                                        @if($item['cost'] > 0)
                                            {{ number_format($item['cost'], 0, ',', ' ') }}
                                        @else
                                            —
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </x-card.card-body>
    </flux:card>
</div>
