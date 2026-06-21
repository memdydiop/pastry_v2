<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Consommation Journalière')] class extends Component {
    use WithPagination;

    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = PER_PAGE;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedDateFrom() { $this->resetPage(); }
    public function updatedDateTo() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $query = InventoryMovement::where('type', InventoryMovementType::OUT)
            ->with('ingredient', 'user');

        if (!empty($this->search)) {
            $query->whereHas('ingredient', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            });
        }
        if (!empty($this->dateFrom)) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if (!empty($this->dateTo)) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $movements = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=consommations-' . now()->format('Y-m-d') . '.csv',
        ];

        $callback = function () use ($movements) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Date', 'Heure', 'Ingrédient', 'Unité', 'Quantité', 'Enregistré par', 'Notes']);

            foreach ($movements as $mvt) {
                fputcsv($handle, [
                    $mvt->created_at->format('d/m/Y'),
                    $mvt->created_at->format('H:i'),
                    $mvt->ingredient?->name ?? '—',
                    $mvt->ingredient?->unit ?? '',
                    number_format($mvt->quantity, 2, ',', ''),
                    $mvt->user?->name ?? '—',
                    $mvt->notes ?? '',
                ]);
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public function with(): array
    {
        $query = InventoryMovement::where('type', InventoryMovementType::OUT)
            ->with('ingredient', 'user');

        if (!empty($this->search)) {
            $query->whereHas('ingredient', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->dateFrom)) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if (!empty($this->dateTo)) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $todayConsumption = InventoryMovement::where('type', InventoryMovementType::OUT)
            ->whereDate('created_at', today())
            ->sum('quantity');

        $filteredTotal = (clone $query)->sum('quantity');

        $ingredientsUsed = (clone $query)
            ->select('ingredient_id')
            ->distinct()
            ->count('ingredient_id');

        return [
            'movements' => $movements,
            'isFiltered' => !empty($this->search) || !empty($this->dateFrom) || !empty($this->dateTo),
            'todayConsumption' => $todayConsumption,
            'filteredTotal' => $filteredTotal,
            'ingredientsUsed' => $ingredientsUsed,
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Consommation Journalière'"
        :subtitle="'Historique des sorties de stock et consommation de matières premières.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Atelier & Production</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Stocks</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Consommation</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-emerald-600 dark:text-emerald-400 font-semibold uppercase tracking-wider">Aujourd'hui</flux:text>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">
                    {{ number_format($todayConsumption, 2, ',', ' ') }}
                </div>
            </div>
        </flux:card>

        <flux:card class="border border-blue-200/80 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-blue-600 dark:text-blue-400 font-semibold uppercase tracking-wider">Total {{ $isFiltered ? '(filtré)' : 'général' }}</flux:text>
                <div class="text-2xl font-black text-blue-700 dark:text-blue-300 mt-1">
                    {{ number_format($filteredTotal, 2, ',', ' ') }}
                </div>
            </div>
        </flux:card>

        <flux:card class="border border-violet-200/80 dark:border-violet-800/60 bg-violet-50/50 dark:bg-violet-950/20">
            <div class="p-4">
                <flux:text size="xs" class="text-violet-600 dark:text-violet-400 font-semibold uppercase tracking-wider">Ingrédients utilisés</flux:text>
                <div class="text-2xl font-black text-violet-700 dark:text-violet-300 mt-1">
                    {{ $ingredientsUsed }}
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header :title="'Historique des Sorties'" :subtitle="'Mouvements de type consommation enregistrés.'">
            <x-slot:menu>
                <flux:menu.item icon="arrow-down-tray" wire:click="exportCsv" class="cursor-pointer">Export CSV</flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Rechercher un ingrédient..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
                <flux:input type="date" wire:model.live="dateFrom" placeholder="Du" size="sm" class="w-full sm:w-36" />
                <flux:input type="date" wire:model.live="dateTo" placeholder="Au" size="sm" class="w-full sm:w-36" />
            </x-card.table-filters>

            @if($movements->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 flex items-center justify-center text-xl mx-auto shadow-xs">
                        📋
                    </div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucune consommation trouvée</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">Aucune sortie de stock enregistrée ou ne correspondant à vos filtres.</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Date</flux:table.column>
                        <flux:table.column>Ingrédient</flux:table.column>
                        <flux:table.column class="text-right">Quantité</flux:table.column>
                        <flux:table.column>Enregistré par</flux:table.column>
                        <flux:table.column>Notes</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($movements as $movement)
                            <flux:table.row :key="$movement->id">
                                <flux:table.cell class="whitespace-nowrap">
                                    <div class="text-sm font-medium">{{ $movement->created_at->format('d/m/Y') }}</div>
                                    <div class="text-xs text-zinc-400">{{ $movement->created_at->format('H:i') }}</div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $movement->ingredient?->name ?? '—' }}
                                    </div>
                                    <div class="text-xs text-zinc-400">{{ $movement->ingredient?->unit?->value ?? '' }}</div>
                                </flux:table.cell>

                                <flux:table.cell class="text-right">
                                    <span class="font-bold text-zinc-900 dark:text-white">
                                        {{ number_format($movement->quantity, 2, ',', ' ') }}
                                    </span>
                                </flux:table.cell>

                                <flux:table.cell class="text-sm text-zinc-500">
                                    {{ $movement->user?->name ?? '—' }}
                                </flux:table.cell>

                                <flux:table.cell class="text-sm text-zinc-400 max-w-xs truncate">
                                    {{ $movement->notes ?? '—' }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($movements->hasPages())
                    <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $movements->links() }}
                    </div>
                @endif
            @endif
        </x-card.card-body>
    </flux:card>
</div>
