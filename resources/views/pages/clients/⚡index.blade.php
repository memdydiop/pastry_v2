<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\Client;
use App\Traits\WithSorting;

new #[Title('Gestion des Clients')] class extends Component {
    use WithPagination;
    use WithSorting;

    public $search = '';
    public $perPage = PER_PAGE;
    public $showDeleteModal = false;
    public $deleteClientId = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    #[On('client-saved')]
    public function refreshClients()
    {
        // Forcer le rafraîchissement
    }

    public function prepareDeleteClient($id)
    {
        $this->deleteClientId = $id;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteClient()
    {
        if (!auth()->user()->hasAnyRole(['ghost', 'Gérant/Admin'])) {
            abort(403, 'Action non autorisée.');
        }

        $client = Client::findOrFail($this->deleteClientId);
        $client->delete();

        $this->showDeleteModal = false;
        $this->dispatch('toast', variant: 'success', heading: 'Client supprimé', text: "La fiche de {$client->name} a été retirée.");
    }

    public function clearFilters(): void
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Client::query();

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('phone', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        return [
            'clients' => $query->paginate($this->perPage)
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading 
        :title="'Annuaire des Clients'" 
        :subtitle="'Gérez le portefeuille clients, leurs coordonnées et l\'historique de l\'atelier.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Dashboards</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Clients</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <flux:card class="relative overflow-hidden border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header :title="'Portefeuille Clients'" :subtitle="'Liste des clients enregistrés et leurs informations de contact.'">
            <x-slot:menu>
                <flux:menu.item icon="plus" x-on:click="$flux.modal('client-form-modal').show()" class="cursor-pointer">
                    Ajouter un client
                </flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <x-card.table-filters 
                search-placeholder="Rechercher par nom ou téléphone..."
                search-binding="search"
            />

            @if($clients->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 flex items-center justify-center text-xl mx-auto shadow-xs">
                        👥
                    </div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucun client trouvé</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">L'annuaire est vide ou aucun résultat ne correspond à votre recherche.</flux:text>
                </div>
            @else
                <flux:table class="w-full">
                    <flux:table.columns>
                        <flux:table.column class="pl-6">Civilité</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortBy === 'name' ? $sortDirection : null" wire:click="sort('name')">Nom / Client</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'phone'" :direction="$sortBy === 'phone' ? $sortDirection : null" wire:click="sort('phone')">Téléphone</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortBy === 'email' ? $sortDirection : null" wire:click="sort('email')">Adresse Email</flux:table.column>
                        <flux:table.column class="hidden md:table-cell">Notes & Préférences</flux:table.column>
                        <flux:table.column class="pr-6"></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($clients as $client)
                            <flux:table.row :key="$client->id">
                                <flux:table.cell class="pl-6">
                                    <flux:badge size="sm" color="zinc" class="font-semibold">
                                        {{ $client->gender ?? '—' }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell class="font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $client->name }}
                                </flux:table.cell>
                                
                                <flux:table.cell class="text-zinc-600 dark:text-zinc-400 font-mono text-xs">
                                    {{ $client->phone }}
                                </flux:table.cell>
                                
                                <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                                    {{ $client->email ?? '—' }}
                                </flux:table.cell>
                                
                                <flux:table.cell class="hidden md:table-cell max-w-xs truncate text-xs text-zinc-400 dark:text-zinc-500 italic">
                                    {{ $client->notes ?? 'Aucune note spécifique' }}
                                </flux:table.cell>

                                <flux:table.cell class="pr-6 text-end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" title="Actions" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil" wire:click="$dispatch('open-client-modal', { id: {{ $client->id }} })">
                                                Modifier
                                            </flux:menu.item>
                                            @if(auth()->user()->hasAnyRole(['ghost', 'Gérant/Admin']))
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger" wire:click="prepareDeleteClient({{ $client->id }})">
                                                    Supprimer la fiche
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($clients->hasPages())
                    <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $clients->links() }}
                    </div>
                @endif
            @endif
        </x-card.card-body>
    </flux:card>

    <flux:modal name="delete-client-modal" wire:model.self="showDeleteModal" class="min-w-[22rem]">
        <form wire:submit="confirmDeleteClient" class="space-y-6">
            <div>
                <flux:heading size="lg">Supprimer ce client ?</flux:heading>
                <flux:text class="mt-2">Cette action est irréversible. Les anciennes factures seront conservées.</flux:text>
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

    <livewire:pages::clients.modals.client-form-modal />
</div>