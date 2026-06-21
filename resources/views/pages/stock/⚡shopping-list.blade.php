<?php

use Livewire\Component;
use App\Enums\OrderStatus;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

new #[Title('Liste de Courses')] class extends Component {
    public $includeStockAlert = true;
    public $periodDays = 14;

    public function getShoppingListProperty()
    {
        $upcomingOrders = Order::notCancelled()
            ->whereIn('status', [
                OrderStatus::CONFIRMÉE,
                OrderStatus::EN_PRODUCTION,
                OrderStatus::PRÊTE,
                OrderStatus::EN_LIVRAISON,
            ])
            ->whereBetween('delivery_due_at', [now(), now()->addDays($this->periodDays)])
            ->with('levels.recipe.recipeIngredients.ingredient')
            ->get();

        $needs = collect();

        foreach ($upcomingOrders as $order) {
            foreach ($order->levels as $level) {
                if (!$level->recipe) {
                    continue;
                }
                foreach ($level->recipe->recipeIngredients as $ri) {
                    $key = $ri->ingredient_id;
                    $needs->put($key, ($needs->get($key, 0)) + $ri->quantity);
                }
            }
        }

        $ingredients = Ingredient::whereIn('id', $needs->keys())->get()->keyBy('id');

        $list = $needs->map(function ($qty, $ingId) use ($ingredients) {
            $ing = $ingredients->get($ingId);
            if (!$ing) {
                return null;
            }
            $needed = $qty;
            $stock = (float) $ing->stock_quantity;
            $deficit = max(0, $needed - $stock);
            return [
                'id' => $ing->id,
                'name' => $ing->name,
                'unit' => $ing->unit,
                'needed' => $needed,
                'stock' => $stock,
                'deficit' => $deficit,
                'alert_threshold' => $ing->alert_threshold,
            ];
        })->filter()->sortByDesc('deficit')->values();

        $alertItems = Ingredient::whereColumn('stock_quantity', '<=', 'alert_threshold')
            ->whereNotIn('id', $needs->keys())
            ->get()
            ->map(function ($ing) {
                return [
                    'id' => $ing->id,
                    'name' => $ing->name,
                    'unit' => $ing->unit,
                    'needed' => 0,
                    'stock' => (float) $ing->stock_quantity,
                    'deficit' => max(0, (float) $ing->alert_threshold - (float) $ing->stock_quantity),
                    'alert_threshold' => $ing->alert_threshold,
                ];
            });

        return [
            'fromOrders' => $list,
            'fromAlerts' => $this->includeStockAlert ? $alertItems : collect(),
        ];
    }

    public function getTotalDeficitProperty()
    {
        $list = $this->shoppingList;
        return $list['fromOrders']->sum('deficit') + $list['fromAlerts']->sum('deficit');
    }

    public function getIngredientCountProperty()
    {
        $list = $this->shoppingList;
        return $list['fromOrders']->count() + $list['fromAlerts']->count();
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Liste de Courses'"
        :subtitle="'Consolidation automatique des besoins en ingrédients pour les commandes à venir.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Atelier & Production</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Stocks</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Liste de courses</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="border border-blue-200/80 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-blue-600 dark:text-blue-400 font-semibold uppercase tracking-wider">Ingrédients à acheter</flux:text>
                <div class="text-2xl font-black text-blue-700 dark:text-blue-300 mt-1">{{ $this->ingredientCount }}</div>
            </div>
        </flux:card>

        <flux:card class="border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-amber-600 dark:text-amber-400 font-semibold uppercase tracking-wider">Période</flux:text>
                <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ $periodDays }} jours</div>
            </div>
        </flux:card>

        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-emerald-600 dark:text-emerald-400 font-semibold uppercase tracking-wider">Déficit estimé</flux:text>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">{{ $this->totalDeficit }}</div>
            </div>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header :title="'Besoins par ingrédient'" :subtitle="'Basé sur les commandes en cours et les stocks actuels.'">
            <x-slot:menu>
                <flux:menu.item icon="arrow-path" wire:click="$refresh" class="cursor-pointer">Actualiser</flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <div class="flex flex-wrap items-center gap-3 p-4 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                <flux:select wire:model.live="periodDays" size="sm" class="w-32">
                    <option value="7">7 jours</option>
                    <option value="14">14 jours</option>
                    <option value="30">30 jours</option>
                    <option value="60">60 jours</option>
                </flux:select>

                <flux:checkbox wire:model.live="includeStockAlert" label="Inclure les alertes stock faible" />
            </div>

            @if($this->ingredientCount === 0)
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 flex items-center justify-center text-xl mx-auto shadow-xs">
                        🛒
                    </div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucun besoin identifié</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">Aucune commande à venir ou stock suffisant pour tous les ingrédients.</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Ingrédient</flux:table.column>
                        <flux:table.column class="text-center">Unité</flux:table.column>
                        <flux:table.column class="text-right">Nécessaire</flux:table.column>
                        <flux:table.column class="text-right">Stock actuel</flux:table.column>
                        <flux:table.column class="text-right">Déficit</flux:table.column>
                        <flux:table.column>Source</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @php $list = $this->shoppingList; @endphp

                        @foreach($list['fromOrders'] as $item)
                            <flux:table.row :key="'order-'.$item['id']">
                                <flux:table.cell>
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $item['name'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="text-center text-zinc-500 text-sm">{{ $item['unit'] }}</flux:table.cell>
                                <flux:table.cell class="text-right font-medium">{{ number_format($item['needed'], 2, ',', ' ') }}</flux:table.cell>
                                <flux:table.cell class="text-right {{ $item['stock'] < $item['alert_threshold'] ? 'text-rose-600 font-bold' : 'text-zinc-500' }}">
                                    {{ number_format($item['stock'], 2, ',', ' ') }}
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-bold {{ $item['deficit'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                    @if($item['deficit'] > 0)
                                        {{ number_format($item['deficit'], 2, ',', ' ') }}
                                    @else
                                        <flux:icon.check variant="micro" class="inline text-emerald-500 size-4" />
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" variant="info">Commandes</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach

                        @foreach($list['fromAlerts'] as $item)
                            <flux:table.row :key="'alert-'.$item['id']" class="opacity-80">
                                <flux:table.cell>
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $item['name'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="text-center text-zinc-500 text-sm">{{ $item['unit'] }}</flux:table.cell>
                                <flux:table.cell class="text-right font-medium">—</flux:table.cell>
                                <flux:table.cell class="text-right text-rose-600 font-bold">
                                    {{ number_format($item['stock'], 2, ',', ' ') }}
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-bold text-rose-600">
                                    {{ number_format($item['deficit'], 2, ',', ' ') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" variant="warning">Stock faible</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </x-card.card-body>
    </flux:card>
</div>
