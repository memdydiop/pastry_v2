<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

new class extends Component {
    public $user_id = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $selected_role = '';
    public $selected_permissions = [];
    public $is_active = true; // Gestion du statut de l'employé
    public $showModal = false;

    protected function rules()
    {
        $rules = [
            'selected_role' => 'required|string|exists:roles,name',
            'selected_permissions' => 'array',
            'selected_permissions.*' => 'string|exists:permissions,name',
            'is_active' => 'boolean',
        ];

        if (!$this->user_id) {
            $rules['name'] = 'required|string|min:2|max:255';
            $rules['email'] = 'required|email|max:255|unique:users,email';
        }

        return $rules;
    }

    #[On('open-create-modal')]
    public function openCreate()
    {
        $this->resetErrorBag();
        $this->reset(['user_id', 'name', 'email', 'selected_role', 'selected_permissions']);
        $this->is_active = true;
        $this->showModal = true;
    }

    #[On('open-edit-modal')]
    public function openEdit($id)
    {
        $this->resetErrorBag();
        $user = User::findOrFail($id);
        
        // Sécurité : Interdiction absolue de toucher au compte développeur
        if ($user->hasRole('ghost')) { 
            abort(403, 'Action non autorisée sur ce compte.'); 
        }

        $this->user_id = $user->id;
        $this->reset(['name', 'email', 'password']);
        $this->is_active = $user->is_active ?? true;
        $this->selected_role = $user->roles->first()?->name ?? '';
        $this->selected_permissions = $user->permissions->pluck('name')->toArray();
        
        $this->showModal = true;
    }

    public function saveUser()
    {
        // Protection : Un utilisateur non admin ne peut pas s'octroyer des droits ou modifier quelqu'un
        if (!auth()->user()->hasAnyRole(['ghost', 'Gérant/Admin'])) {
            abort(403, 'Habilitation insuffisante.');
        }

        $this->validate();

        // Blocage de sécurité rôles réservés
        if (in_array($this->selected_role, ['ghost'])) {
            abort(403, 'Ce rôle est réservé au système.');
        }

        if ($this->user_id) {
            // MODE: MODIFICATION
            $user = User::findOrFail($this->user_id);
            
            // Éviter qu'un gérant ne se retire accidentellement son propre accès Admin
            if ($user->id === auth()->id() && $this->selected_role !== 'Gérant/Admin' && $user->hasRole('Gérant/Admin')) {
                $this->addError('selected_role', 'Vous ne pouvez pas vous retirer votre propre rôle d’Administrateur.');
                return;
            }

            // Empêcher l'auto-désactivation
            if ($user->id === auth()->id() && !$this->is_active) {
                $this->addError('is_active', 'Vous ne pouvez pas désactiver votre propre compte.');
                return;
            }

            $user->is_active = (bool) $this->is_active;
            $user->save();

            // Synchronisation complète Rôle + Permissions directes
            $user->syncRoles([$this->selected_role]);
            $user->syncPermissions($this->selected_permissions);
            
            $this->dispatch('toast', variant: 'success', heading: 'Employé mis à jour', text: "Le profil de {$user->name} a été reconfiguré.");
        } else {
            // MODE: CRÉATION
            $token = Str::random(64);

            $newUser = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make(Str::random(32)),
                'is_active' => false,
                'setup_token' => $token,
                'setup_token_sent_at' => now(),
            ]);

            $newUser->assignRole($this->selected_role);
            if (!empty($this->selected_permissions)) {
                $newUser->givePermissionTo($this->selected_permissions);
            }

            $newUser->notify(new AccountInvitation($token));

            $this->dispatch('toast', variant: 'success', heading: 'Employé créé', text: "Un email d'invitation a été envoyé à {$newUser->name}.");
        }

        $this->showModal = false;
        $this->dispatch('user-saved');
    }

    public function with(): array
    {
        return [
            'roles' => Role::where('name', '!=', 'ghost')->get(),
            // On récupère les permissions pour pouvoir les attribuer individuellement si besoin
            'permissions' => Permission::all() 
        ];
    }
}; ?>

<div>
    <flux:modal name="user-form-modal" wire:model="showModal" class="md:w-[550px] space-y-6">
        <form wire:submit.prevent="saveUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $user_id ? 'Modifier le collaborateur' : 'Enregistrer un collaborateur' }}</flux:heading>
                <flux:subheading>Définissez les accréditations, la sécurité et l'accès aux modules de l'atelier.</flux:subheading>
            </div>

            @if(!$user_id)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Nom & Prénoms" placeholder="Ex: Koffi Emmanuel" required />
                    <flux:input type="email" wire:model="email" label="Email Professionnel" placeholder="koffi@patisserie.com" required />
                </div>

                <flux:select wire:model="selected_role" label="Rôle Principal (Spatie)" placeholder="Sélectionner un rôle..." required>
                    @foreach($roles as $roleItem)
                        <option value="{{ $roleItem->name }}">{{ $roleItem->name }}</option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="selected_role" label="Rôle Principal (Spatie)" placeholder="Sélectionner un rôle..." required>
                    @foreach($roles as $roleItem)
                        <option value="{{ $roleItem->name }}">{{ $roleItem->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            {{-- Module d'attribution de permissions d'appoint --}}
            <flux:separator text="Permissions spécifiques d'appoint (Optionnel)" />
            
            <div class="bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
                <flux:text size="xs" class="text-zinc-500 mb-3 block">Cochez des droits additionnels sans modifier le rôle principal de l'employé :</flux:text>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($permissions as $perm)
                        <label class="flex items-center gap-2 p-2 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-xs font-medium text-zinc-700 dark:text-zinc-300 cursor-pointer">
                            <input type="checkbox" wire:model="selected_permissions" value="{{ $perm->name }}" class="rounded border-zinc-300 dark:border-zinc-700 text-zinc-900 focus:ring-zinc-900">
                            {{ $perm->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Statut du compte --}}
            {{-- Statut du compte --}}
            <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-100 dark:border-zinc-800/60">
                <div>
                    <flux:label for="is_active_toggle" class="cursor-pointer">Autoriser l'accès à l'application</flux:label>
                    <flux:text size="xs" class="text-zinc-400">Si désactivé, l'employé sera immédiatement éjecté et bloqué.</flux:text>
                </div>
                <div class="relative flex items-center">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer" id="is_active_toggle">
                    <label for="is_active_toggle" class="relative w-11 h-6 bg-zinc-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-zinc-600 peer-checked:bg-emerald-500 cursor-pointer"></label>
                </div>
            </div>
            @error('is_active') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            
            <div class="flex gap-2 justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Sauvegarder la fiche</flux:button>
            </div>
        </form>
    </flux:modal>
</div>