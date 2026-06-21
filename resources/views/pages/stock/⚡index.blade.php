<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\IngredientUnit;
use App\Enums\InventoryMovementType;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use App\Traits\WithSorting;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Gestion des Stocks')] class extends Component {
    use WithPagination;
    use WithSorting;

    public $search = '';
    public $showCritical = false;
    public $perPage = PER_PAGE;

    public $showAddModal = false;
    public $showIncomingModal = false;
    public $showConsumptionModal = false;
    public $showLossModal = false;
    public $editIngredientId = null;

    public $name = '';
    public $unit = 'kg';
    public $alert_threshold = 0;
    public $is_critical = false;
    public $notes = '';

    public $incoming_ingredient_id = '';
    public $incoming_quantity = 0;
    public $incoming_unit_price = null;
    public $incoming_supplier_id = '';
    public $incoming_notes = '';

    public $consumption = [];

    public $loss_ingredient_id = '';
    public $loss_quantity = 0;
    public $loss_reason = 'gaspillage';

    // --- Adjust properties ---
    public $showAdjustModal = false;
    public $adjust_ingredient_id = '';
    public $adjust_quantity = 0;
    public $adjust_reason = 'inventaire';

    // --- History modal ---
    public $showHistoryModal = false;
    public $historyMovements = [];
    public $historyIngredientName = '';

    // --- Produce modal ---
    public $showProduceModal = false;
    public $produce_recipe_id = '';
    public $produce_quantity = 1;
    public $produceRecipeIngredients = [];
    public $produceRecipeName = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedShowCritical() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function openAddModal($id = null)
    {
        $this->resetErrorBag();
        $this->editIngredientId = $id;

        if ($id) {
            $ing = Ingredient::findOrFail($id);
            $this->name = $ing->name;
            $this->unit = $ing->unit;
            $this->alert_threshold = $ing->alert_threshold;
            $this->is_critical = $ing->is_critical;
            $this->notes = $ing->notes;
        } else {
            $this->reset(['name', 'unit', 'alert_threshold', 'is_critical', 'notes']);
            $this->unit = 'kg';
        }

        $this->showAddModal = true;
    }

    public function saveIngredient()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'unit' => ['required', Rule::enum(IngredientUnit::class)],
            'alert_threshold' => 'required|numeric|min:0',
            'is_critical' => 'boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $data = [
            'name' => $this->name,
            'unit' => $this->unit,
            'stock_quantity' => 0,
            'alert_threshold' => $this->alert_threshold,
            'is_critical' => $this->is_critical,
            'notes' => $this->notes,
        ];

        if ($this->editIngredientId) {
            Ingredient::findOrFail($this->editIngredientId)->update($data);
            $this->dispatch('toast', variant: 'success', heading: 'Ingrédient mis à jour.');
        } else {
            Ingredient::create($data);
            $this->dispatch('toast', variant: 'success', heading: 'Ingrédient ajouté.');
        }

        $this->showAddModal = false;
    }

    public function openIncomingModal()
    {
        $this->resetErrorBag();
        $this->reset([
            'incoming_ingredient_id', 'incoming_quantity', 'incoming_unit_price',
            'incoming_supplier_id', 'incoming_notes',
        ]);
        $this->incoming_quantity = 0;
        $this->showIncomingModal = true;
    }

    public function recordIncoming()
    {
        $this->validate([
            'incoming_ingredient_id' => 'required|exists:ingredients,id',
            'incoming_quantity' => 'required|numeric|min:0.01',
            'incoming_unit_price' => 'nullable|numeric|min:0',
            'incoming_supplier_id' => 'required|exists:suppliers,id',
            'incoming_notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () {
            $ing = Ingredient::findOrFail($this->incoming_ingredient_id);
            $ing->increment('stock_quantity', $this->incoming_quantity);

            if ($this->incoming_unit_price) {
                $ing->update(['unit_price' => $this->incoming_unit_price]);
            }

            $ing->movements()->create([
                'type' => InventoryMovementType::IN,
                'quantity' => $this->incoming_quantity,
                'unit_price' => $this->incoming_unit_price ?: null,
                'notes' => $this->incoming_notes,
                'user_id' => auth()->id(),
                'supplier_id' => $this->incoming_supplier_id,
            ]);
        });

        $this->dispatch('toast', variant: 'success', heading: 'Entrée de stock enregistrée.');
        $this->showIncomingModal = false;
    }

    public function openConsumptionModal()
    {
        $this->resetErrorBag();

        $ingredients = Ingredient::where('stock_quantity', '>', 0)->orderBy('name')->get();

        if ($ingredients->isEmpty()) {
            $this->dispatch('toast', variant: 'warning', heading: 'Aucun ingrédient en stock à consommer.');
            return;
        }

        $this->consumption = $ingredients->mapWithKeys(fn($i) => [$i->id => 0])->toArray();

        $this->showConsumptionModal = true;
    }

    public function recordConsumption()
    {
        $this->validate([
            'consumption' => 'required|array',
            'consumption.*' => 'numeric|min:0',
        ]);

        $toSave = collect($this->consumption)->filter(fn($qty) => floatval($qty) > 0);

        if ($toSave->isEmpty()) {
            $this->addError('consumption', 'Saisissez au moins une quantité.');
            return;
        }

        foreach ($toSave as $id => $qty) {
            $ing = Ingredient::findOrFail($id);
            if ($ing->stock_quantity < $qty) {
                $this->addError('consumption', "Stock insuffisant pour {$ing->name} ({$ing->stock_quantity} {$ing->unit->value} disponible).");
                return;
            }
        }

        DB::transaction(function () use ($toSave) {
            foreach ($toSave as $id => $qty) {
                $ing = Ingredient::findOrFail($id);
                $ing->decrement('stock_quantity', $qty);

                $ing->movements()->create([
                    'type' => InventoryMovementType::OUT,
                    'quantity' => $qty,
                    'notes' => 'Consommation du ' . now()->format('d/m/Y'),
                    'user_id' => auth()->id(),
                ]);
            }
        });

        $this->checkAndNotifyStockAlert();

        $this->dispatch('toast', variant: 'success', heading: 'Consommation du jour enregistrée (' . $toSave->count() . ' ingrédients).');
        $this->showConsumptionModal = false;
    }

    public function openLossModal()
    {
        $this->resetErrorBag();
        $this->reset(['loss_ingredient_id', 'loss_quantity', 'loss_reason']);
        $this->loss_quantity = 0;
        $this->loss_reason = 'gaspillage';
        $this->showLossModal = true;
    }

    public function recordLoss()
    {
        $this->validate([
            'loss_ingredient_id' => 'required|exists:ingredients,id',
            'loss_quantity' => 'required|numeric|min:0.01',
            'loss_reason' => 'required|string|in:casse,perime,gaspillage,ratage,degustation,souillure,autre',
        ]);

        $ing = Ingredient::findOrFail($this->loss_ingredient_id);

        if ($ing->stock_quantity < $this->loss_quantity) {
            $this->addError('loss_quantity', "Stock insuffisant pour {$ing->name} ({$ing->stock_quantity} {$ing->unit->value} disponible).");
            return;
        }

        DB::transaction(function () use ($ing) {
            $ing->decrement('stock_quantity', $this->loss_quantity);

            $reasonLabels = [
                'casse' => 'Casse',
                'perime' => 'Périmé',
                'gaspillage' => 'Gaspillage/versé',
                'ratage' => 'Erreur production',
                'degustation' => 'Dégustation/échantillon',
                'souillure' => 'Souillure/tombé',
                'autre' => 'Autre',
            ];

            $ing->movements()->create([
                'type' => InventoryMovementType::LOSS,
                'quantity' => $this->loss_quantity,
                'notes' => 'Perte : ' . ($reasonLabels[$this->loss_reason] ?? $this->loss_reason),
                'user_id' => auth()->id(),
            ]);
        });

        $this->checkAndNotifyStockAlert();

        $this->dispatch('toast', variant: 'warning', heading: 'Perte enregistrée.');
        $this->showLossModal = false;
    }

    public function openAdjustModal()
    {
        $this->resetErrorBag();
        $this->reset(['adjust_ingredient_id', 'adjust_quantity', 'adjust_reason']);
        $this->adjust_quantity = 0;
        $this->adjust_reason = 'inventaire';
        $this->showAdjustModal = true;
    }

    public function recordAdjust()
    {
        $this->validate([
            'adjust_ingredient_id' => 'required|exists:ingredients,id',
            'adjust_quantity' => 'required|numeric',
            'adjust_reason' => 'required|string|max:500',
        ]);

        $ing = Ingredient::findOrFail($this->adjust_ingredient_id);

        DB::transaction(function () use ($ing) {
            $ing->increment('stock_quantity', $this->adjust_quantity);

            $ing->movements()->create([
                'type' => InventoryMovementType::ADJUST,
                'quantity' => $this->adjust_quantity,
                'notes' => 'Ajustement : ' . $this->adjust_reason,
                'user_id' => auth()->id(),
            ]);
        });

        $this->dispatch('toast', variant: 'info', heading: 'Ajustement enregistré.');
        $this->showAdjustModal = false;
    }

    public function openHistoryModal($ingredientId)
    {
        $this->resetErrorBag();
        $ing = Ingredient::findOrFail($ingredientId);
        $this->historyIngredientName = $ing->name;
        $this->historyMovements = $ing->movements()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();
        $this->showHistoryModal = true;
    }

    public function openProduceModal()
    {
        $this->resetErrorBag();
        $this->reset(['produce_recipe_id', 'produce_quantity', 'produceRecipeIngredients', 'produceRecipeName']);
        $this->produce_quantity = 1;
        $this->showProduceModal = true;
    }

    public function updatedProduceRecipeId($value)
    {
        $this->reset(['produceRecipeIngredients', 'produceRecipeName']);
        if (empty($value)) {
            return;
        }
        $recipe = Recipe::with('recipeIngredients.ingredient')->findOrFail($value);
        $this->produceRecipeName = $recipe->name;
        $this->produceRecipeIngredients = $recipe->recipeIngredients->map(function ($ri) {
            $ings = Ingredient::find($ri->ingredient_id);
            return [
                'id' => $ri->ingredient_id,
                'name' => $ri->ingredient->name ?? '?',
                'unit' => $ri->ingredient->unit ?? '',
                'qty_per_unit' => $ri->quantity,
                'stock' => $ings?->stock_quantity ?? 0,
                'sufficient' => ($ings?->stock_quantity ?? 0) >= ($ri->quantity * $this->produce_quantity),
            ];
        })->toArray();
    }

    public function updatedProduceQuantity()
    {
        if (!empty($this->produce_recipe_id) && !empty($this->produceRecipeIngredients)) {
            $this->produceRecipeIngredients = collect($this->produceRecipeIngredients)->map(function ($item) {
                $ing = Ingredient::find($item['id']);
                $item['sufficient'] = ($ing?->stock_quantity ?? 0) >= ($item['qty_per_unit'] * $this->produce_quantity);
                return $item;
            })->toArray();
        }
    }

    public function recordProduction()
    {
        $this->validate([
            'produce_recipe_id' => 'required|exists:recipes,id',
            'produce_quantity' => 'required|integer|min:1',
        ]);

        $recipe = Recipe::with('recipeIngredients.ingredient')->findOrFail($this->produce_recipe_id);

        $toDeduct = collect($this->produceRecipeIngredients);

        foreach ($toDeduct as $item) {
            if (!$item['sufficient']) {
                $this->addError('produce_quantity', "Stock insuffisant pour {$item['name']}.");
                return;
            }
        }

        DB::transaction(function () use ($recipe, $toDeduct) {
            foreach ($toDeduct as $item) {
                $qty = $item['qty_per_unit'] * $this->produce_quantity;
                $ing = Ingredient::findOrFail($item['id']);
                $ing->decrement('stock_quantity', $qty);

                $ing->movements()->create([
                    'type' => InventoryMovementType::OUT,
                    'quantity' => $qty,
                    'notes' => "Production : {$recipe->name} x{$this->produce_quantity}",
                    'user_id' => auth()->id(),
                ]);
            }
        });

        $this->checkAndNotifyStockAlert();

        $this->dispatch('toast', variant: 'success', heading: "Production enregistrée : {$recipe->name} x{$this->produce_quantity}");
        $this->showProduceModal = false;
    }

    public function exportCsv(): StreamedResponse
    {
        $ingredients = Ingredient::orderBy('name')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=stocks-' . now()->format('Y-m-d') . '.csv',
        ];

        $callback = function () use ($ingredients) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Ingrédient', 'Unité', 'Stock', 'Seuil Alerte', 'Critique', 'Prix Unitaire', 'Valeur Stock', 'Statut']);

            foreach ($ingredients as $ing) {
                $isLow = $ing->stock_quantity <= $ing->alert_threshold;
                $status = $ing->is_critical && $isLow ? 'CRITIQUE' : ($isLow ? 'Faible' : 'OK');
                $value = ($ing->unit_price ?? 0) * $ing->stock_quantity;

                fputcsv($handle, [
                    $ing->name,
                    $ing->unit,
                    number_format($ing->stock_quantity, 2, ',', ''),
                    number_format($ing->alert_threshold, 2, ',', ''),
                    $ing->is_critical ? 'Oui' : 'Non',
                    number_format($ing->unit_price ?? 0, 0, ',', ''),
                    number_format($value, 0, ',', ''),
                    $status,
                ]);
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public function checkAndNotifyStockAlert(): void
    {
        $criticalIngredients = Ingredient::where('is_critical', true)
            ->whereColumn('stock_quantity', '<=', 'alert_threshold')
            ->get();

        if ($criticalIngredients->isEmpty()) {
            return;
        }

        $users = User::role('Gérant/Admin')
            ->orWhere('is_active', true)
            ->get()
            ->unique('id');

        foreach ($criticalIngredients as $ingredient) {
            Notification::send($users, new StockAlertNotification(
                ingredient: $ingredient,
                currentStock: (float) $ingredient->stock_quantity,
                triggeredBy: auth()->user()->name,
            ));
        }
    }

    public function getLossReasonsProperty(): array
    {
        return [
            'casse' => 'Casse (œuf, vaisselle…)',
            'perime' => 'Produit périmé',
            'gaspillage' => 'Gaspillage / versé',
            'ratage' => 'Erreur de production (fournée brûlée, crème tranchée…)',
            'degustation' => 'Dégustation / échantillon offert',
            'souillure' => 'Souillure / tombé par terre',
            'autre' => 'Autre',
        ];
    }

    public function getConsumableIngredientsProperty()
    {
        return Ingredient::where('stock_quantity', '>', 0)->orderBy('name')->get();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'showCritical']);
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Ingredient::query();

        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->orderBy('name');
        }

        if (!empty($this->search)) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->showCritical) {
            $query->where('is_critical', true)
                  ->whereColumn('stock_quantity', '<=', 'alert_threshold');
        }

        $ingredients = $query->paginate($this->perPage);

        return [
            'ingredients' => $ingredients,
            'isFiltered' => !empty($this->search) || $this->showCritical,
            'allIngredients' => Ingredient::orderBy('name')->get(),
            'allSuppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'totalIngredients' => Ingredient::count(),
            'lowStockCount' => Ingredient::whereColumn('stock_quantity', '<=', 'alert_threshold')->count(),
            'criticalCount' => Ingredient::where('is_critical', true)
                ->whereColumn('stock_quantity', '<=', 'alert_threshold')
                ->count(),
            'totalStockValue' => Ingredient::select(DB::raw('SUM(COALESCE(unit_price, 0) * stock_quantity) as total'))->value('total') ?? 0,
            'allRecipes' => Recipe::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Gestion des Stocks'"
        :subtitle="'Suivi des matières premières, entrées de stock et consommation journalière.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Atelier & Production</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Stocks</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="border border-blue-200/80 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-blue-600 dark:text-blue-400 text-xs font-semibold uppercase tracking-wider">Ingrédients</flux:text>
                <div class="text-2xl font-black text-blue-700 dark:text-blue-300 mt-1">{{ $totalIngredients }}</div>
            </x-card.card-body>
        </flux:card>

        <flux:card class="border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-amber-600 dark:text-amber-400 text-xs font-semibold uppercase tracking-wider">Stock Faible</flux:text>
                <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ $lowStockCount }}</div>
            </x-card.card-body>
        </flux:card>

        <flux:card class="border border-rose-200/80 dark:border-rose-800/60 bg-rose-50/50 dark:bg-rose-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-rose-600 dark:text-rose-400 text-xs font-semibold uppercase tracking-wider">Alerte Critique</flux:text>
                <div class="text-2xl font-black text-rose-700 dark:text-rose-300 mt-1">{{ $criticalCount }}</div>
            </x-card.card-body>
        </flux:card>

        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-emerald-600 dark:text-emerald-400 text-xs font-semibold uppercase tracking-wider">Valorisation Stock</flux:text>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">
                    {{ number_format($totalStockValue, 0, ',', ' ') }}
                    <span class="text-sm font-normal">FCFA</span>
                </div>
            </x-card.card-body>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header title="Inventaire" subtitle="Liste des matières premières et niveau de stock actuel">
            <x-slot:menu>
                <flux:menu.item icon="plus" wire:click="openAddModal" class="cursor-pointer">Nouvel ingrédient</flux:menu.item>
                <flux:menu.item icon="arrow-trending-up" wire:click="openIncomingModal" class="cursor-pointer">Entrée de stock</flux:menu.item>
                <flux:menu.item icon="arrow-trending-down" wire:click="openConsumptionModal" class="cursor-pointer">Consommation du jour</flux:menu.item>
                <flux:menu.item icon="exclamation-triangle" wire:click="openLossModal" class="cursor-pointer">Pertes (casse, périmé…)</flux:menu.item>
                <flux:menu.item icon="adjustments-horizontal" wire:click="openAdjustModal" class="cursor-pointer">Ajustement d'inventaire</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item icon="clock" href="{{ route('stock.consumption') }}" class="cursor-pointer">Historique des consommations</flux:menu.item>
                <flux:menu.item icon="cake" wire:click="openProduceModal" class="cursor-pointer">Produire une recette</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item icon="shopping-cart" href="{{ route('stock.shopping-list') }}" class="cursor-pointer">Liste de courses</flux:menu.item>
                <flux:menu.item icon="chart-bar" href="{{ route('stock.efficiency') }}" class="cursor-pointer">Rapport d'efficacité</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item icon="arrow-down-tray" wire:click="exportCsv" class="cursor-pointer">Export CSV</flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Rechercher un ingrédient..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
                <flux:checkbox wire:model.live="showCritical" label="Alertes critiques uniquement" class="text-sm" />
            </x-card.table-filters>

            @if($ingredients->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 flex items-center justify-center text-xl mx-auto shadow-xs">📦</div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucun ingrédient trouvé</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400">Ajoutez votre premier ingrédient pour commencer.</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortBy === 'name' ? $sortDirection : null" wire:click="sort('name')">Ingrédient</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'unit'" :direction="$sortBy === 'unit' ? $sortDirection : null" wire:click="sort('unit')" class="text-center">Unité</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'stock_quantity'" :direction="$sortBy === 'stock_quantity' ? $sortDirection : null" wire:click="sort('stock_quantity')" class="text-right">Stock</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'alert_threshold'" :direction="$sortBy === 'alert_threshold' ? $sortDirection : null" wire:click="sort('alert_threshold')" class="text-right">Seuil Alerte</flux:table.column>
                        <flux:table.column>Statut</flux:table.column>
                        <flux:table.column class="text-right">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($ingredients as $ing)
                            @php
                                $isLow = $ing->stock_quantity <= $ing->alert_threshold;
                                $badgeVariant = $ing->is_critical && $isLow ? 'danger' : ($isLow ? 'warning' : 'success');
                                $badgeText = $ing->is_critical && $isLow ? 'CRITIQUE' : ($isLow ? 'Faible' : 'OK');
                            @endphp
                            <flux:table.row :key="$ing->id" class="{{ $ing->is_critical && $isLow ? 'bg-rose-50/50 dark:bg-rose-950/10' : '' }}">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $ing->name }}
                                        @if($ing->is_critical)
                                            <flux:icon.exclamation-triangle variant="micro" class="inline text-rose-500 size-3.5 align-middle ml-1" title="Matière critique" />
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="text-center text-zinc-500 text-sm">{{ $ing->unit }}</flux:table.cell>

                                <flux:table.cell class="text-right font-bold {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-white' }}">
                                    {{ number_format($ing->stock_quantity, 2, ',', ' ') }}
                                </flux:table.cell>

                                <flux:table.cell class="text-right text-zinc-400 text-sm">{{ number_format($ing->alert_threshold, 2, ',', ' ') }}</flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge :variant="$badgeVariant" size="sm" class="px-2 py-0.5">{{ $badgeText }}</flux:badge>
                                </flux:table.cell>

                                <flux:table.cell class="text-right">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" title="Actions" />
                                        <flux:menu>
                                            <flux:menu.item icon="clock" wire:click="openHistoryModal({{ $ing->id }})">
                                                Historique
                                            </flux:menu.item>
                                            <flux:menu.item icon="pencil-square" wire:click="openAddModal({{ $ing->id }})">
                                                Modifier
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($ingredients->hasPages())
                    <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $ingredients->links() }}
                    </div>
                @endif
            @endif
        </x-card.card-body>
    </flux:card>

    <flux:modal name="add-ingredient-modal" wire:model="showAddModal" class="max-w-lg space-y-6">
        <form wire:submit.prevent="saveIngredient" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editIngredientId ? "Modifier l'ingrédient" : 'Nouvel ingrédient' }}</flux:heading>
                <flux:subheading>Ajoutez une matière première à votre inventaire.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" label="Nom de l'ingrédient" placeholder="Ex: Farine de blé" required />

                <flux:select wire:model="unit" label="Unité de mesure" required>
                    @foreach(App\Enums\IngredientUnit::cases() as $u)
                        <option value="{{ $u->value }}">{{ $u->value }}</option>
                    @endforeach
                </flux:select>

                <flux:input type="number" wire:model="alert_threshold" label="Seuil d'alerte" required step="0.01" />

                <flux:switch wire:model="is_critical" label="Matière critique (beurre doux...)" />

                <flux:textarea wire:model="notes" label="Notes" rows="2" />
            </div>

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showAddModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Enregistrer</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="incoming-modal" wire:model="showIncomingModal" class="max-w-lg space-y-6">
        <form wire:submit.prevent="recordIncoming" class="space-y-6">
            <div>
                <flux:heading size="lg">Entrée de stock</flux:heading>
                <flux:subheading>Enregistrez un réapprovisionnement avec sa provenance.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="incoming_ingredient_id" label="Ingrédient" placeholder="Sélectionner..." required>
                    @foreach($allIngredients as $ing)
                        <flux:select.option value="{{ $ing->id }}">{{ $ing->name }} ({{ number_format($ing->stock_quantity, 1) }} {{ $ing->unit }} en stock)</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="number" wire:model="incoming_quantity" label="Quantité reçue" required step="0.01" min="0.01" />

                <flux:input type="number" wire:model="incoming_unit_price" label="Prix unitaire (FCFA)" step="0.01" />

                <flux:select wire:model="incoming_supplier_id" label="Provenance" placeholder="Sélectionner la source..." required>
                    @foreach($allSuppliers as $sup)
                        <flux:select.option value="{{ $sup->id }}">
                            {{ $sup->name }}@if($sup->category) · {{ $sup->category->label() }}@endif
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea wire:model="incoming_notes" label="Notes (numéro de lot, facture…)" rows="2" />
            </div>

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showIncomingModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Valider l'entrée</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="consumption-modal" wire:model="showConsumptionModal" class="max-w-2xl space-y-6">
        <form wire:submit.prevent="recordConsumption" class="space-y-6">
            <div>
                <flux:heading size="lg">Consommation du jour</flux:heading>
                <flux:subheading>Saisissez les quantités d'ingrédients utilisées aujourd'hui ({{ now()->format('d/m/Y') }}).</flux:subheading>
                @error('consumption') <flux:text class="text-rose-500 text-sm mt-1">{{ $message }}</flux:text> @enderror
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($this->consumableIngredients as $ing)
                    <div class="flex items-center gap-3 bg-zinc-50 dark:bg-zinc-950/20 p-3 rounded-lg border border-zinc-200/60 dark:border-zinc-800/50">
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $ing->name }}</span>
                            <span class="text-xs text-zinc-400 ml-2">Stock: {{ number_format($ing->stock_quantity, 1) }} {{ $ing->unit }}</span>
                            @if($ing->is_critical)
                                <flux:icon.exclamation-triangle variant="micro" class="inline text-rose-500 size-3" />
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:input type="number" wire:model="consumption.{{ $ing->id }}" min="0" step="0.01" class="w-28 text-sm" placeholder="0" />
                            <span class="text-xs text-zinc-400 w-8">{{ $ing->unit }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showConsumptionModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer" icon="check">
                    Valider la consommation
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="loss-modal" wire:model="showLossModal" class="max-w-lg space-y-6">
        <form wire:submit.prevent="recordLoss" class="space-y-6">
            <div>
                <flux:heading size="lg">Enregistrer une perte</flux:heading>
                <flux:subheading>Casse d'œufs, produit périmé, gaspillage, versé…</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="loss_ingredient_id" label="Ingrédient" placeholder="Sélectionner..." required>
                    @foreach($allIngredients as $ing)
                        <flux:select.option value="{{ $ing->id }}">
                            {{ $ing->name }} ({{ number_format($ing->stock_quantity, 1) }} {{ $ing->unit }} en stock)
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="number" wire:model="loss_quantity" label="Quantité perdue" required step="0.01" min="0.01" />

                <flux:select wire:model="loss_reason" label="Motif" required>
                    @foreach($this->lossReasons as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showLossModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="danger" class="cursor-pointer" icon="exclamation-triangle">
                    Valider la perte
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="adjust-modal" wire:model="showAdjustModal" class="max-w-lg space-y-6">
        <form wire:submit.prevent="recordAdjust" class="space-y-6">
            <div>
                <flux:heading size="lg">Ajustement d'inventaire</flux:heading>
                <flux:subheading>Corrigez le stock après un comptage physique. Utilisez une valeur positive pour augmenter, négative pour diminuer.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="adjust_ingredient_id" label="Ingrédient" placeholder="Sélectionner..." required>
                    @foreach($allIngredients as $ing)
                        <flux:select.option value="{{ $ing->id }}">
                            {{ $ing->name }} ({{ number_format($ing->stock_quantity, 1) }} {{ $ing->unit }} en stock)
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="number" wire:model="adjust_quantity" label="Écart (+ ou -)" required step="0.01" placeholder="Ex: -0.5 ou 2" />

                <flux:input wire:model="adjust_reason" label="Motif de l'ajustement" placeholder="Ex: Inventaire mensuel, erreur de saisie…" required />
            </div>

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showAdjustModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer" icon="adjustments-horizontal">
                    Valider l'ajustement
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="history-modal" wire:model="showHistoryModal" class="max-w-2xl space-y-6">
        <div>
            <flux:heading size="lg">Historique — {{ $historyIngredientName }}</flux:heading>
            <flux:subheading>Derniers mouvements enregistrés pour cet ingrédient.</flux:subheading>
        </div>

        @if(empty($historyMovements))
            <flux:text class="text-zinc-400">Aucun mouvement enregistré.</flux:text>
        @else
            <div class="overflow-y-auto max-h-96">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Date</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column class="text-right">Quantité</flux:table.column>
                        <flux:table.column>Par</flux:table.column>
                        <flux:table.column>Notes</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($historyMovements as $mvt)
                            @php
                                $typeVariant = match($mvt->type->value) {
                                    'in' => 'success',
                                    'out' => 'warning',
                                    'loss' => 'danger',
                                    'adjust' => 'info',
                                    default => 'neutral',
                                };
                                $typeLabel = match($mvt->type->value) {
                                    'in' => 'Entrée',
                                    'out' => 'Sortie',
                                    'loss' => 'Perte',
                                    'adjust' => 'Ajustement',
                                    default => $mvt->type->value,
                                };
                            @endphp
                            <flux:table.row :key="$mvt->id">
                                <flux:table.cell class="whitespace-nowrap text-sm">
                                    {{ $mvt->created_at->format('d/m/Y H:i') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :variant="$typeVariant">{{ $typeLabel }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-bold">
                                    {{ $mvt->quantity > 0 ? '+' : '' }}{{ number_format($mvt->quantity, 2, ',', ' ') }}
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $mvt->user?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-400 max-w-xs truncate">{{ $mvt->notes ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
            <flux:button variant="ghost" wire:click="$set('showHistoryModal', false)">Fermer</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="produce-modal" wire:model="showProduceModal" class="max-w-lg space-y-6">
        <form wire:submit.prevent="recordProduction" class="space-y-6">
            <div>
                <flux:heading size="lg">Produire une recette</flux:heading>
                <flux:subheading>Sélectionnez une recette et la quantité à produire. Les ingrédients seront déduits automatiquement du stock.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:select wire:model.live="produce_recipe_id" label="Recette" placeholder="Choisir une recette..." required>
                    @foreach($allRecipes as $recipe)
                        <flux:select.option value="{{ $recipe->id }}">{{ $recipe->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if(!empty($produceRecipeName))
                    <flux:input type="number" wire:model.live="produce_quantity" label="Quantité" min="1" step="1" required />

                    <div class="space-y-2">
                        <flux:text size="sm" class="font-semibold text-zinc-700 dark:text-zinc-300">Ingrédients nécessaires (x{{ $produce_quantity }})</flux:text>
                        @foreach($produceRecipeIngredients as $item)
                            <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-950/20 p-2.5 rounded-lg border {{ $item['sufficient'] ? 'border-zinc-200/60 dark:border-zinc-800/50' : 'border-rose-200 dark:border-rose-800/50 bg-rose-50/50 dark:bg-rose-950/10' }}">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($item['sufficient'])
                                        <flux:icon.check variant="micro" class="text-emerald-500 size-4 shrink-0" />
                                    @else
                                        <flux:icon.x-mark variant="micro" class="text-rose-500 size-4 shrink-0" />
                                    @endif
                                    <span class="text-sm {{ $item['sufficient'] ? 'text-zinc-800 dark:text-zinc-200' : 'text-rose-700 dark:text-rose-300' }}">
                                        {{ $item['name'] }}
                                    </span>
                                </div>
                                <span class="text-sm font-medium whitespace-nowrap ml-2">
                                    {{ number_format($item['qty_per_unit'] * $produce_quantity, 2, ',', ' ') }} {{ $item['unit'] }}
                                    <span class="text-xs text-zinc-400">(stock: {{ number_format($item['stock'], 1, ',', ' ') }})</span>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @error('produce_quantity')
                        <flux:text class="text-rose-500 text-sm">{{ $message }}</flux:text>
                    @enderror
                @endif
            </div>

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showProduceModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer" icon="cake">
                    Produire
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
