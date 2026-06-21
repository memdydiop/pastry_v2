<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\User;
use App\Traits\WithSorting;
use App\Notifications\AccountInvitation;
use Spatie\Permission\Models\Role;

new #[Title('Team settings')] class extends Component {
    use WithPagination;
    use WithSorting;

    // Propriétés de filtrage uniquement
    public $search = '';
    public $roleFilter = '';
    public $perPage = PER_PAGE;

    public $showResendModal = false;
    public $resendUserId = null;
    public $resendEmail = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedRoleFilter() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'roleFilter']);
        $this->resetPage();
    }

    public function boot()
    {
        if (!auth()->user()->hasAnyRole(['ghost', 'Gérant/Admin'])) {
            abort(403, 'Accès refusé.');
        }
    }

    // Écoute l'enregistrement réussi depuis la modal autonome pour rafraîchir le tableau
    #[On('user-saved')]
    public function refreshTable()
    {
        // Force le rafraîchissement du composant sans recharger la page
    }

    public function openCreateModal()
    {
        $this->dispatch('open-create-modal');
    }

    public function openEditModal($id)
    {
        $this->dispatch('open-edit-modal', id: $id);
    }

    public function prepareResend($id)
    {
        $user = User::findOrFail($id);

        if ($user->setup_completed_at) {
            $this->dispatch('toast', variant: 'warning', heading: 'Cet utilisateur a déjà activé son compte.');
            return;
        }

        $this->resendUserId = $user->id;
        $this->resendEmail = $user->email;
        $this->showResendModal = true;
    }

    public function confirmResend()
    {
        $user = User::findOrFail($this->resendUserId);

        $rules = ['resendEmail' => 'required|email|max:255'];
        if ($this->resendEmail !== $user->email) {
            $rules['resendEmail'] = 'required|email|max:255|unique:users,email';
        }
        $this->validate($rules);

        if ($user->setup_completed_at) {
            $this->dispatch('toast', variant: 'warning', heading: 'Cet utilisateur a déjà activé son compte.');
            $this->showResendModal = false;
            return;
        }

        if ($this->resendEmail !== $user->email) {
            $user->email = $this->resendEmail;
        }

        $token = \Illuminate\Support\Str::random(64);
        $user->setup_token = $token;
        $user->setup_token_sent_at = now();
        $user->save();

        $user->notify(new AccountInvitation($token));

        $this->showResendModal = false;
        $this->dispatch('toast', variant: 'success', heading: 'Invitation renvoyée', text: "Un email d'invitation a été envoyé à {$user->name}.");
    }

    public $showToggleModal = false;
    public $toggleUserId = null;

    public function prepareToggleStatus($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            $this->dispatch('toast', variant: 'error', heading: 'Vous ne pouvez pas désactiver votre propre compte.');
            return;
        }

        $this->toggleUserId = $user->id;
        $this->showToggleModal = true;
    }

    public function confirmToggleStatus()
    {
        $user = User::findOrFail($this->toggleUserId);

        if ($user->id === auth()->id()) {
            $this->dispatch('toast', variant: 'error', heading: 'Vous ne pouvez pas désactiver votre propre compte.');
            $this->showToggleModal = false;
            return;
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'réactivé' : 'désactivé';
        $this->showToggleModal = false;
        $this->dispatch('toast', variant: 'success', heading: "Compte de {$user->name} {$status}.");
    }

    public function with(): array
    {
        // Optimisation Eager Loading (with) pour éviter 50 requêtes SQL dans la boucle
        $query = User::with(['roles', 'permissions'])->whereDoesntHave('roles', function($q) { 
            $q->where('name', 'ghost'); 
        });

        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('email', 'ilike', '%' . $this->search . '%');
            });
        }

        if (!empty($this->roleFilter)) {
            $query->whereHas('roles', function($q) {
                $q->where('name', $this->roleFilter);
            });
        }

        return [
            'users' => $query->when($this->sortBy, fn($q) => $q->orderBy($this->sortBy, $this->sortDirection), fn($q) => $q->orderBy('name', 'asc'))->paginate($this->perPage),
            'roles' => Role::where('name', '!=', 'ghost')->get(),
            'isFiltered' => !empty($this->search) || !empty($this->roleFilter)
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading 
        :title="'Gestion de l\'Équipe'" 
        :subtitle="'Configurez les accès au système et affectez les rôles aux collaborateurs de l\'atelier.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Configuration</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Paramètres Équipe</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <flux:card class="relative overflow-hidden group border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        
        <x-card.card-header :title="'Personnel de la Pâtisserie'" :subtitle="'Liste des comptes utilisateurs et permissions système active.'">
            <x-slot:menu>
                <flux:menu.item icon="plus" wire:click="openCreateModal" x-on:click="$flux.modal('user-form-modal').show()" class="cursor-pointer">
                    Ajouter un employé
                </flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            
            <x-card.table-filters 
                search-placeholder="Rechercher par nom, email..."
                search-binding="search"
                per-page-binding="perPage"
                :has-active-filters="$isFiltered"
            >
                <flux:select wire:model.live="roleFilter" size="sm" class="w-40!">
                    <option value="">Tous les rôles</option>
                    @foreach($roles as $roleItem)
                        <option value="{{ $roleItem->name }}">{{ $roleItem->name }}</option>
                    @endforeach
                </flux:select>
            </x-card.table-filters>

            <flux:table class="w-full">
                    <flux:table.columns>
                        <flux:table.column class="pl-6" sortable :sorted="$sortBy === 'name'" :direction="$sortBy === 'name' ? $sortDirection : null" wire:click="sort('name')">Nom de l'employé</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortBy === 'email' ? $sortDirection : null" wire:click="sort('email')">Email / Identifiant</flux:table.column>
                        <flux:table.column>Rôle attribué</flux:table.column>
                        <flux:table.column>Invitation</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'is_active'" :direction="$sortBy === 'is_active' ? $sortDirection : null" wire:click="sort('is_active')">Statut</flux:table.column>
                        <flux:table.column class="pr-6"></flux:table.column>
                    </flux:table.columns>
                
                <flux:table.rows>
                    @forelse($users as $employee)
                        <flux:table.row :key="$employee->id">
                            <flux:table.cell class="truncate pl-6 font-semibold text-sm text-zinc-900 dark:text-white">
                                {{ $employee->name }}
                            </flux:table.cell>
                            
                            <flux:table.cell class="truncate text-zinc-500 dark:text-zinc-400">
                                {{ $employee->email }}
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <flux:badge size="sm" variant="subtle" color="zinc">
                                    {{ $employee->roles->first()?->name ?? 'Aucun' }}
                                </flux:badge>
                            </flux:table.cell>

                            {{-- Colonne Statut d'invitation --}}
                            <flux:table.cell>
                                @php
                                    $isExpired = $employee->setup_token && !$employee->setup_completed_at && $employee->setup_token_sent_at && $employee->setup_token_sent_at->addMinutes(INVITATION_EXPIRY_TIME)->isPast();
                                @endphp
                                @if($employee->setup_completed_at)
                                    <flux:badge size="sm" variant="subtle" color="emerald" class="px-2 py-0.5">Acceptée</flux:badge>
                                @elseif($isExpired)
                                    <flux:badge size="sm" variant="subtle" color="red" class="px-2 py-0.5">Expirée</flux:badge>
                                @elseif($employee->setup_token)
                                    <flux:badge size="sm" variant="subtle" color="amber" class="px-2 py-0.5">En attente</flux:badge>
                                @else
                                    <flux:text size="xs" class="text-zinc-400">—</flux:text>
                                @endif
                            </flux:table.cell>

                            {{-- Colonne de Statut interactive visuellement --}}
                            <flux:table.cell>
                                @if($employee->setup_token && !$employee->setup_completed_at)
                                    <flux:badge size="sm" variant="solid" color="amber" class="px-2 py-0.5">En attente</flux:badge>
                                @elseif(!$employee->is_active)
                                    <flux:badge size="sm" variant="solid" color="red" class="px-2 py-0.5">Suspendu</flux:badge>
                                @else
                                    <flux:badge size="sm" variant="solid" color="emerald" class="px-2 py-0.5">Actif</flux:badge>
                                @endif
                            </flux:table.cell>
                            
                            <flux:table.cell class="pr-6 text-end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" class="cursor-pointer" title="Actions" />
                                    <flux:menu>
                                        <flux:menu.item icon="eye"
                                            :href="route('users.show', $employee)"
                                            wire:navigate>
                                            Voir le profil
                                        </flux:menu.item>
                                        <flux:menu.item icon="pencil-square"
                                            wire:click="openEditModal({{ $employee->id }})"
                                            x-on:click="$flux.modal('user-form-modal').show()">
                                            Modifier
                                        </flux:menu.item>
                                        @if($employee->setup_token && !$employee->setup_completed_at)
                                            <flux:menu.item icon="paper-airplane"
                                                wire:click="prepareResend({{ $employee->id }})">
                                                Renvoyer l'invitation
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                        @endif
                                        @if(!$employee->setup_token || $employee->setup_completed_at)
                                        <flux:menu.item icon="{{ $employee->is_active ? 'lock-closed' : 'check-circle' }}"
                                            wire:click="prepareToggleStatus({{ $employee->id }})">
                                            {{ $employee->is_active ? 'Désactiver' : 'Réactiver' }}
                                        </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-500 dark:text-zinc-400 py-12">
                                Aucun membre enregistré ou trouvé dans le système.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            
            @if($users->hasPages())
                <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                    {{ $users->links() }}
                </div>
            @endif
        </x-card.card-body>
    </flux:card>

    <flux:modal name="resend-invitation-modal" wire:model.self="showResendModal" class="min-w-[22rem]">
        <form wire:submit="confirmResend" class="space-y-6">
            <div>
                <flux:heading size="lg">Renvoyer l'invitation ?</flux:heading>
                <flux:text class="mt-2">
                    Vérifiez ou corrigez l'email avant de renvoyer l'invitation.
                </flux:text>
            </div>

            <flux:input wire:model="resendEmail" label="Email" type="email" required />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Annuler</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Envoyer</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="toggle-status-modal" wire:model.self="showToggleModal" class="min-w-[22rem]">
        @php $toggleUser = $toggleUserId ? User::find($toggleUserId) : null; @endphp
        @if($toggleUser)
        <form wire:submit="confirmToggleStatus" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $toggleUser->is_active ? 'Désactiver' : 'Réactiver' }} le compte ?
                </flux:heading>
                <flux:text class="mt-2">
                    @if($toggleUser->is_active)
                        L'employé <strong>{{ $toggleUser->name }}</strong> sera immédiatement éjecté et ne pourra plus se connecter.
                    @else
                        L'employé <strong>{{ $toggleUser->name }}</strong> pourra de nouveau accéder à l'application.
                    @endif
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Annuler</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="{{ $toggleUser->is_active ? 'danger' : 'primary' }}">
                    {{ $toggleUser->is_active ? 'Désactiver' : 'Réactiver' }}
                </flux:button>
            </div>
        </form>
        @endif
    </flux:modal>

    {{-- APPEL DE LA MODAL AUTONOME SÉPARÉE --}}
    <livewire:pages::settings.modals.user-form-modal />
</div>