<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\DeliveryPartner;
use App\Traits\WithSorting;
use Illuminate\Validation\Rule;

new #[Title('Livreurs & Services')] class extends Component {
    use WithPagination;
    use WithSorting;

    public $search = '';
    public $showInactive = false;
    public $perPage = PER_PAGE;

    public $showModal = false;
    public $editingPartnerId = null;
    public $showDeleteModal = false;
    public $deletePartnerId = null;

    public $name = '';
    public $phone = '';
    public $email = '';
    public $vehicle_type = '';
    public $base_rate = null;
    public $notes = '';
    public $is_active = true;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedShowInactive() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function openModal($id = null)
    {
        $this->resetErrorBag();
        $this->editingPartnerId = $id;

        if ($id) {
            $partner = DeliveryPartner::findOrFail($id);
            $this->name = $partner->name;
            $this->phone = $partner->phone;
            $this->email = $partner->email;
            $this->vehicle_type = $partner->vehicle_type;
            $this->base_rate = $partner->base_rate;
            $this->notes = $partner->notes;
            $this->is_active = $partner->is_active;
        } else {
            $this->reset(['name', 'phone', 'email', 'vehicle_type', 'base_rate', 'notes']);
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'vehicle_type' => 'nullable|string|max:100',
            'base_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'vehicle_type' => $this->vehicle_type,
            'base_rate' => $this->base_rate,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
        ];

        if ($this->editingPartnerId) {
            DeliveryPartner::findOrFail($this->editingPartnerId)->update($data);
            $this->dispatch('toast', variant: 'success', heading: 'Livreur mis à jour.');
        } else {
            DeliveryPartner::create($data);
            $this->dispatch('toast', variant: 'success', heading: 'Livreur ajouté.');
        }

        $this->showModal = false;
    }

    public function prepareDelete($id)
    {
        $this->deletePartnerId = $id;
        $this->showDeleteModal = true;
    }

    public function confirmDelete()
    {
        $this->authorize('gerantOrGhost');

        DeliveryPartner::findOrFail($this->deletePartnerId)->delete();
        $this->showDeleteModal = false;
        $this->dispatch('toast', variant: 'success', heading: 'Livreur supprimé.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'showInactive']);
        $this->resetPage();
    }

    public function with(): array
    {
        $query = DeliveryPartner::query();

        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->orderBy('name');
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('phone', 'like', '%' . $this->search . '%');
            });
        }

        if (!$this->showInactive) {
            $query->where('is_active', true);
        }

        $partners = $query->paginate($this->perPage);

        return [
            'partners' => $partners,
            'isFiltered' => !empty($this->search),
            'totalActive' => DeliveryPartner::where('is_active', true)->count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Livreurs & Services'"
        :subtitle="'Gestion des partenaires de livraison et services de transport.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Approvisionnement</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Livreurs</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="border border-blue-200/80 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-blue-600 dark:text-blue-400 text-xs font-semibold uppercase tracking-wider">Livreurs actifs</flux:text>
                <div class="text-2xl font-black text-blue-700 dark:text-blue-300 mt-1">{{ $totalActive }}</div>
            </x-card.card-body>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header title="Partenaires de livraison" subtitle="Livreurs, coursiers et services de transport">
            <x-slot:menu>
                <flux:menu.item icon="plus" wire:click="openModal" class="cursor-pointer">Nouveau livreur</flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Nom ou téléphone..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
                <flux:checkbox wire:model.live="showInactive" label="Voir les inactifs" class="text-sm" />
            </x-card.table-filters>

            @if($partners->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 flex items-center justify-center text-xl mx-auto shadow-xs">🚚</div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucun livreur trouvé</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400">Ajoutez votre premier livreur ou service de livraison.</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortBy === 'name' ? $sortDirection : null" wire:click="sort('name')">Nom</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'phone'" :direction="$sortBy === 'phone' ? $sortDirection : null" wire:click="sort('phone')">Téléphone</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortBy === 'email' ? $sortDirection : null" wire:click="sort('email')">Email</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'vehicle_type'" :direction="$sortBy === 'vehicle_type' ? $sortDirection : null" wire:click="sort('vehicle_type')">Véhicule</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'base_rate'" :direction="$sortBy === 'base_rate' ? $sortDirection : null" wire:click="sort('base_rate')" class="text-right">Tarif de base</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'is_active'" :direction="$sortBy === 'is_active' ? $sortDirection : null" wire:click="sort('is_active')">Statut</flux:table.column>
                        <flux:table.column class="text-right">Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($partners as $partner)
                            <flux:table.row :key="$partner->id">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $partner->name }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm">{{ $partner->phone }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $partner->email ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $partner->vehicle_type ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="text-right text-sm">
                                    {{ $partner->base_rate ? number_format($partner->base_rate, 0, ',', ' ') . ' F' : '—' }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :variant="$partner->is_active ? 'success' : 'neutral'" size="sm">
                                        {{ $partner->is_active ? 'Actif' : 'Inactif' }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" title="Actions" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="openModal({{ $partner->id }})">
                                                Modifier
                                            </flux:menu.item>
                                            @if($partner->is_active)
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger" wire:click="prepareDelete({{ $partner->id }})">
                                                    Supprimer
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($partners->hasPages())
                    <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $partners->links() }}
                    </div>
                @endif
            @endif
        </x-card.card-body>
    </flux:card>

    <flux:modal name="delete-partner-modal" wire:model.self="showDeleteModal" class="min-w-[22rem]">
        <form wire:submit="confirmDelete" class="space-y-6">
            <div>
                <flux:heading size="lg">Supprimer ce livreur ?</flux:heading>
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

    <flux:modal name="delivery-partner-modal" wire:model="showModal" class="max-w-lg space-y-6">
        <form wire:submit.prevent="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingPartnerId ? "Modifier le livreur" : 'Nouveau livreur' }}</flux:heading>
                <flux:subheading>Ajoutez un partenaire de livraison.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" label="Nom du livreur / service" placeholder="Ex: Express Delivery" required />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="phone" label="Téléphone" placeholder="+225 00 00 00 00" required />
                    <flux:input wire:model="email" label="Email" type="email" placeholder="contact@exemple.com" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="vehicle_type" label="Type de véhicule" placeholder="Moto, camion…" />
                    <flux:input wire:model="base_rate" label="Tarif de base (FCFA)" type="number" step="0.01" min="0" />
                </div>

                <flux:textarea wire:model="notes" label="Notes" rows="2" />

                <flux:switch wire:model="is_active" label="Actif" />
            </div>

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Enregistrer</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
