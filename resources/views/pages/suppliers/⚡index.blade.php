<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\SupplierCategory;
use App\Models\Supplier;
use App\Traits\WithSorting;
use Illuminate\Validation\Rule;

new #[Title('Sources d\'Approvisionnement')] class extends Component {
    use WithPagination;
    use WithSorting;

    public $search = '';
    public $showInactive = false;
    public $categoryFilter = '';
    public $perPage = PER_PAGE;

    public $showModal = false;
    public $editingSupplierId = null;
    public $showDeleteModal = false;
    public $deleteSupplierId = null;

    public $name = '';
    public $category = '';
    public $contact_name = '';
    public $phone = '';
    public $email = '';
    public $address = '';
    public $notes = '';
    public $is_active = true;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedCategoryFilter() { $this->resetPage(); }
    public function updatedShowInactive() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function openModal($id = null)
    {
        $this->resetErrorBag();
        $this->editingSupplierId = $id;

        if ($id) {
            $supplier = Supplier::findOrFail($id);
            $this->name = $supplier->name;
            $this->category = $supplier->category?->value ?? '';
            $this->contact_name = $supplier->contact_name;
            $this->phone = $supplier->phone;
            $this->email = $supplier->email;
            $this->address = $supplier->address;
            $this->notes = $supplier->notes;
            $this->is_active = $supplier->is_active;
        } else {
            $this->reset(['name', 'category', 'contact_name', 'phone', 'email', 'address', 'notes']);
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::enum(SupplierCategory::class)],
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $this->name,
            'category' => $this->category,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
        ];

        if ($this->editingSupplierId) {
            Supplier::findOrFail($this->editingSupplierId)->update($data);
            $this->dispatch('toast', variant: 'success', heading: 'Source mise à jour.');
        } else {
            Supplier::create($data);
            $this->dispatch('toast', variant: 'success', heading: 'Source ajoutée.');
        }

        $this->showModal = false;
    }

    public function prepareDelete($id)
    {
        $this->deleteSupplierId = $id;
        $this->showDeleteModal = true;
    }

    public function confirmDelete()
    {
        $this->authorize('gerantOrGhost');

        $supplier = Supplier::find($this->deleteSupplierId);
        if (!$supplier) return;

        $incomingCount = $supplier->incomingMovements()->count();
        if ($incomingCount > 0) {
            $supplier->update(['is_active' => false]);
            $this->dispatch('toast', variant: 'warning', heading: 'Source désactivée', description: 'Des mouvements de stock sont liés à cette source.');
            $this->showDeleteModal = false;
            return;
        }

        $supplier->delete();
        $this->showDeleteModal = false;
        $this->dispatch('toast', variant: 'success', heading: 'Source supprimée.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'showInactive']);
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Supplier::query();

        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->orderBy('category')->orderBy('name');
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('contact_name', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->categoryFilter)) {
            $query->where('category', $this->categoryFilter);
        }

        if (!$this->showInactive) {
            $query->where('is_active', true);
        }

        return [
            'suppliers' => $query->paginate($this->perPage),
            'isFiltered' => !empty($this->search) || !empty($this->categoryFilter) || !$this->showInactive,
            'totalSuppliers' => Supplier::count(),
            'categories' => SupplierCategory::cases(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Sources d\'Approvisionnement'"
        :subtitle="'Fournisseurs, supermarchés, marchés, boutiques de quartier et grossistes.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Approvisionnement</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Sources</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header title="Sources" subtitle="Tous vos lieux d'achat">
            <x-slot:menu>
                <flux:menu.item icon="plus" wire:click="openModal" class="cursor-pointer">Nouvelle source</flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Rechercher une source..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
                <flux:select wire:model.live="categoryFilter" placeholder="Toutes catégories" size="sm" class="max-w-48">
                    @foreach($categories as $cat)
                        <flux:select.option value="{{ $cat->value }}">{{ $cat->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:checkbox wire:model.live="showInactive" label="Voir inactives" class="text-sm" />
            </x-card.table-filters>

            @if($suppliers->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 flex items-center justify-center text-xl mx-auto shadow-xs">🏪</div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucune source trouvée</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400">Ajoutez votre première source d'approvisionnement.</flux:text>
                </div>
            @else
                <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortBy === 'name' ? $sortDirection : null" wire:click="sort('name')">Nom</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'category'" :direction="$sortBy === 'category' ? $sortDirection : null" wire:click="sort('category')">Catégorie</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'contact_name'" :direction="$sortBy === 'contact_name' ? $sortDirection : null" wire:click="sort('contact_name')">Contact</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'phone'" :direction="$sortBy === 'phone' ? $sortDirection : null" wire:click="sort('phone')">Téléphone</flux:table.column>
                    <flux:table.column class="text-right">Actions</flux:table.column>
                </flux:table.columns>

                    <flux:table.rows>
                        @foreach($suppliers as $sup)
                            <flux:table.row :key="$sup->id"
                                class="{{ !$sup->is_active ? 'opacity-50' : '' }}">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $sup->name }}
                                        @if(!$sup->is_active)
                                            <flux:badge size="sm" variant="danger" class="ml-2">Inactif</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if($sup->category)
                                        <flux:badge :variant="$sup->category->badgeVariant()" size="sm" class="px-2 py-0.5">
                                            {{ $sup->category->label() }}
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="text-sm text-zinc-500">
                                    {{ $sup->contact_name ?: '—' }}
                                </flux:table.cell>

                                <flux:table.cell class="text-sm text-zinc-500 font-mono">
                                    {{ $sup->phone ?: '—' }}
                                </flux:table.cell>

                                <flux:table.cell class="text-right">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" title="Actions" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="openModal({{ $sup->id }})">
                                                Modifier
                                            </flux:menu.item>
                                            <flux:menu.item icon="trash" variant="danger" wire:click="prepareDelete({{ $sup->id }})">
                                                Supprimer
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($suppliers->hasPages())
                    <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $suppliers->links() }}
                    </div>
                @endif
            @endif
        </x-card.card-body>
    </flux:card>

    <flux:modal name="delete-supplier-modal" wire:model.self="showDeleteModal" class="min-w-[22rem]">
        <form wire:submit="confirmDelete" class="space-y-6">
            <div>
                <flux:heading size="lg">Supprimer cette source ?</flux:heading>
                <flux:text class="mt-2">Cette action est irréversible.</flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Annuler</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Supprimer</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="supplier-modal" wire:model="showModal" class="max-w-lg space-y-6">
        <form wire:submit.prevent="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingSupplierId ? "Modifier la source" : 'Nouvelle source' }}</flux:heading>
                <flux:subheading>Marché, supermarché, fournisseur ou boutique de quartier.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" label="Nom" placeholder="Ex: Auchan Cap Sud, Marché de Treichville" required />

                <flux:select wire:model="category" label="Catégorie" placeholder="Sélectionner..." required>
                    @foreach($categories as $cat)
                        <flux:select.option value="{{ $cat->value }}">{{ $cat->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="contact_name" label="Contact" placeholder="Nom du vendeur" />
                    <flux:input wire:model="phone" label="Téléphone" placeholder="+225 01 02 03 04 05" />
                </div>

                <flux:input wire:model="email" label="Email" type="email" placeholder="contact@exemple.com" />
                <flux:textarea wire:model="address" label="Adresse" placeholder="Quartier, ville..." rows="2" />
                <flux:textarea wire:model="notes" label="Notes" rows="2" />
                <flux:switch wire:model="is_active" label="Source active" />
            </div>

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Enregistrer</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
