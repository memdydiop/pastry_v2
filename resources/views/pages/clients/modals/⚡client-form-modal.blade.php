<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Client;

new class extends Component {
    public $client_id = null;
    public $name = '';
    public $phone = '';
    public $email = '';
    public $gender = ''; // <-- Nouveau champ
    public $notes = '';
    public $showModal = false;

    // protected $rules = [
    //     'name' => 'required|string|min:2|max:255',
    //     'phone' => 'required|string|max:20',
    //     'email' => 'nullable|email|max:255',
    //     'notes' => 'nullable|string',
    // ];

    public function saveClient()
    {
        $this->validate([
            'name' => 'required|string|min:2|max:255',
            'phone' => 'required|string|max:20|unique:clients,phone' . ($this->client_id ? ',' . $this->client_id : ''),
            'email' => 'nullable|email|max:255',
            'gender' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'notes' => $this->notes,
        ];

        if ($this->client_id) {
            $client = Client::findOrFail($this->client_id);
            $client->update($data);
            $this->dispatch('toast', variant: 'success', heading: 'Client mis à jour');
        } else {
            $client = Client::create($data);
            $this->dispatch('toast', variant: 'success', heading: 'Client créé', text: "{$client->name} a été ajouté.");
        }

        $this->showModal = false;
        $this->dispatch('client-saved', clientId: $client->id);
    }

    #[On('open-client-modal')]
    public function openModal($id = null)
    {
        $this->resetErrorBag();
        $this->reset(['client_id', 'name', 'phone', 'email', 'gender', 'notes']); // <-- Réinitialisation incluse
        
        if ($id) {
            $client = Client::findOrFail($id);
            $this->client_id = $client->id;
            $this->name = $client->name;
            $this->phone = $client->phone;
            $this->email = $client->email;
            $this->gender = $client->gender?->value ?? '';
            $this->notes = $client->notes;
        }

        $this->showModal = true;
    }
}; ?>

<div>
    <flux:modal name="client-form-modal" wire:model="showModal" class="md:w-[450px] space-y-6">
        <form wire:submit.prevent="saveClient" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $client_id ? 'Modifier la fiche client' : 'Nouveau Client' }}</flux:heading>
                <flux:subheading>Renseignez les coordonnées pour le suivi des commandes de l'atelier.</flux:subheading>
            </div>

            <div class="space-y-4">
                {{-- Sélecteur de Genre / Civilité inséré en haut --}}
                <flux:select wire:model="gender" label="Civilité / Genre" placeholder="Sélectionner..." required>
                    <option value="Mme">Madame (Mme)</option>
                    <option value="M">Monsieur (M)</option>
                </flux:select>

                <flux:input wire:model="name" label="Nom complet / Entreprise" placeholder="Ex: Marie-Esther Koffi" required />
                <flux:input wire:model="phone" label="Numéro de Téléphone" placeholder="Ex: +225 07..." required />
                <flux:input type="email" wire:model="email" label="Adresse Email (Optionnel)" placeholder="client@gmail.com" />
                <flux:textarea wire:model="notes" label="Notes ou préférences (Optionnel)" placeholder="Ex: Moins de sucre, allergies..." rows="2" />
            </div>

            <div class="flex gap-2 justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Enregistrer</flux:button>
            </div>
        </form>
    </flux:modal>
</div>