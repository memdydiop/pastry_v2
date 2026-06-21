<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
new #[Title('Paramètres Système')] class extends Component {
    use WithPagination;
    use WithFileUploads;

    public string $company_name = '';
    public string $mail_from_address = '';
    public string $notification_email = '';
    public string $default_alert_threshold = '2';
    public string $invitation_expiry_time = '2880';
    public $logo;
    public bool $showTemplateModal = false;
    public ?int $editingTemplateId = null;
    public bool $showDeleteTemplateModal = false;
    public ?int $deleteTemplateId = null;
    public string $template_key = '';
    public string $template_label = '';
    public string $template_message = '';
    public bool $template_is_active = true;

    protected function rules()
    {
        return [
            'company_name' => 'required|string|max:255',
            'mail_from_address' => 'nullable|email|max:255',
            'notification_email' => 'nullable|email|max:255',
            'default_alert_threshold' => 'required|numeric|min:0',
            'invitation_expiry_time' => 'required|integer|min:1',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
        ];
    }

    public function mount(): void
    {
        $this->company_name = Setting::getValue('company_name', 'Pâtisserie Sur Mesure');
        $this->mail_from_address = Setting::getValue('mail_from_address', '');
        $this->notification_email = Setting::getValue('notification_email', '');
        $this->default_alert_threshold = Setting::getValue('default_alert_threshold', '2');
        $this->invitation_expiry_time = Setting::getValue('invitation_expiry_time', '2880');
    }

    public function save()
    {
        $this->validate();

        Setting::setValue('company_name', $this->company_name);
        Setting::setValue('mail_from_address', $this->mail_from_address);
        Setting::setValue('notification_email', $this->notification_email);
        Setting::setValue('default_alert_threshold', $this->default_alert_threshold);
        Setting::setValue('invitation_expiry_time', $this->invitation_expiry_time);

        if ($this->logo) {
            $path = $this->logo->store('logo', 'public');
            Setting::setValue('company_logo', $path);
            $this->logo = null;
        }

        $this->dispatch('toast', variant: 'success', heading: 'Paramètres enregistrés.');
    }

    public function removeLogo(): void
    {
        $path = Setting::getValue('company_logo', '');
        if ($path) {
            Storage::disk('public')->delete($path);
            Setting::setValue('company_logo', '');
        }
    }

    public function openTemplateModal(?int $id = null): void
    {
        $this->resetErrorBag();
        $this->editingTemplateId = $id;

        if ($id) {
            $template = WhatsAppTemplate::findOrFail($id);
            $this->template_key = $template->key;
            $this->template_label = $template->label;
            $this->template_message = $template->message;
            $this->template_is_active = $template->is_active;
        } else {
            $this->reset(['template_key', 'template_label', 'template_message']);
            $this->template_is_active = true;
        }

        $this->showTemplateModal = true;
    }

    public function saveTemplate()
    {
        $this->validate([
            'template_key' => 'required|string|max:100|unique:whatsapp_templates,key,' . ($this->editingTemplateId ?? 'NULL'),
            'template_label' => 'required|string|max:255',
            'template_message' => 'required|string',
            'template_is_active' => 'boolean',
        ]);

        $data = [
            'key' => $this->template_key,
            'label' => $this->template_label,
            'message' => $this->template_message,
            'is_active' => $this->template_is_active,
        ];

        if ($this->editingTemplateId) {
            WhatsAppTemplate::findOrFail($this->editingTemplateId)->update($data);
            $this->dispatch('toast', variant: 'success', heading: 'Template WhatsApp mis à jour.');
        } else {
            WhatsAppTemplate::create($data);
            $this->dispatch('toast', variant: 'success', heading: 'Template WhatsApp ajouté.');
        }

        $this->showTemplateModal = false;
    }

    public function prepareDeleteTemplate(int $id): void
    {
        $this->deleteTemplateId = $id;
        $this->showDeleteTemplateModal = true;
    }

    public function confirmDeleteTemplate(): void
    {
        $this->authorize('gerantOrGhost');
        WhatsAppTemplate::findOrFail($this->deleteTemplateId)->delete();
        $this->showDeleteTemplateModal = false;
        $this->dispatch('toast', variant: 'success', heading: 'Template WhatsApp supprimé.');
    }

    #[Computed]
    public function templates(): Collection
    {
        if (!Schema::hasTable('whatsapp_templates')) {
            return collect();
        }

        return WhatsAppTemplate::orderBy('key')->get();
    }

    #[On('template-saved')]
    public function refreshTemplates(): void
    {
        unset($this->templates);
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Paramètres Système'"
        :subtitle="'Configuration générale de l\'application'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Paramètres</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Système</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs">
        <x-card.card-header title="Configuration générale" subtitle="Modifiez les paramètres de base de l'application" />

        <x-card.card-body class="p-6">
            <form wire:submit.prevent="save" class="space-y-6">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    
                    <div class="col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <flux:input wire:model="company_name" label="Nom de l'entreprise" placeholder="Pâtisserie Sur Mesure" required />

                        <flux:input wire:model="mail_from_address" label="Email expéditeur (invitations & notifications)" type="email" placeholder="ne-pas-repondre@patisserie.fr" hint="Adresse utilisée pour envoyer les emails d'invitation et d'alerte. Laissez vide pour utiliser celle du fichier .env" />

                        <flux:input wire:model="notification_email" label="Email de contact (factures)" type="email" placeholder="contact@patisserie.fr" />

                        <flux:input wire:model="default_alert_threshold" label="Seuil d'alerte par défaut" type="number" step="0.01" min="0" required />

                        <flux:input wire:model="invitation_expiry_time" label="Validité de l'invitation (minutes)" type="number" min="1" required hint="Durée en minutes avant expiration du lien d'invitation. 2880 = 48h." />
                    </div>

                    <div class="col-span-1 flex flex-col  items-center gap-2">
                        <flux:label>Logo de l'entreprise</flux:label>
                        <flux:description>Format PNG, JPG, WebP ou SVG. Max 2 Mo.</flux:description>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            Cliquez sur le logo pour le changer.
                        </flux:text>

                        @php
                            $currentLogo = Setting::getValue('company_logo', '');
                        @endphp

                        <div class="flex items-center gap-4">
                            <div class="relative group shrink-0">
                                {{-- Logo display / preview --}}
                                <div class="flex items-center justify-center size-32 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-800 overflow-hidden">
                                    @if($logo)
                                        <img src="{{ $logo->temporaryUrl() }}" alt="Aperçu" class="size-full object-contain p-1" />
                                    @elseif($currentLogo)
                                        <img src="{{ Storage::url($currentLogo) }}" alt="Logo" class="size-full object-contain p-1" />
                                    @else
                                        <x-app-logo-icon class="size-8 fill-current text-zinc-400 dark:text-zinc-500" />
                                    @endif
                                </div>

                                {{-- Hover overlay to change --}}
                                <label for="logo-upload" class="absolute inset-0 flex items-center justify-center rounded-xl bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer">
                                    <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                    </svg>
                                </label>
                                <input id="logo-upload" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden">
                                @error('logo')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror

                                
                            </div>
                        </div>

                        <div>
                            @if($currentLogo)
                                    <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeLogo" wire:confirm="Supprimer le logo ?" class="cursor-pointer">
                                        Enlever le logo
                                    </flux:button>
                                @endif
                        </div>
                    </div>

                </div>
                    <flux:separator class="my-4" />
                <div class="flex gap-2 pt-2">
                    <flux:button type="submit" variant="primary" class="cursor-pointer">Enregistrer</flux:button>
                </div>
            </form>
        </x-card.card-body>
    </flux:card>

    <!-- Templates WhatsApp -->
    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs">
        <x-card.card-header title="Templates WhatsApp" subtitle="Messages personnalisables pour le Quick Share">
            <x-slot:actions>
                <flux:button wire:click="openTemplateModal()" variant="primary" size="sm" class="cursor-pointer">
                    + Nouveau template
                </flux:button>
            </x-slot:actions>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Clé</flux:table.column>
                    <flux:table.column>Label</flux:table.column>
                    <flux:table.column>Message</flux:table.column>
                    <flux:table.column>Actif</flux:table.column>
                    <flux:table.column class="text-right">Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->templates as $template)
                        <flux:table.row>
                            <flux:table.cell class="font-mono text-xs">{{ $template->key }}</flux:table.cell>
                            <flux:table.cell>{{ $template->label }}</flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate text-xs text-zinc-500">{{ $template->message }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$template->is_active ? 'emerald' : 'zinc'" size="sm">
                                    {{ $template->is_active ? 'Oui' : 'Non' }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:button wire:click="openTemplateModal({{ $template->id }})" size="sm" variant="ghost" icon="pencil" class="cursor-pointer" />
                                <flux:button wire:click="prepareDeleteTemplate({{ $template->id }})" size="sm" variant="ghost" icon="trash" class="cursor-pointer text-red-500" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-card.card-body>
    </flux:card>

    <flux:modal name="delete-template-modal" wire:model.self="showDeleteTemplateModal" class="min-w-[22rem]">
        <form wire:submit="confirmDeleteTemplate" class="space-y-6">
            <div>
                <flux:heading size="lg">Supprimer ce template ?</flux:heading>
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

    <!-- Modal Template WhatsApp -->
    <flux:modal name="template-modal" wire:model="showTemplateModal" class="max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingTemplateId ? 'Modifier' : 'Nouveau' }} template WhatsApp</flux:heading>

            <form wire:submit.prevent="saveTemplate" class="space-y-4">
                <flux:input wire:model="template_key" label="Clé unique" placeholder="order_contact" :disabled="$editingTemplateId !== null" required />
                <flux:input wire:model="template_label" label="Label" placeholder="Contact client - Commande" required />
                <flux:textarea wire:model="template_message" label="Message" placeholder="Bonjour {client_name}, je vous contacte à propos de votre commande {reference}." rows="4" required />
                <flux:text class="text-xs text-zinc-500">
                    Variables disponibles : <code>{client_name}</code>, <code>{reference}</code>, <code>{total_amount}</code>
                </flux:text>
                <flux:checkbox wire:model="template_is_active" label="Template actif" />

                <div class="flex gap-2 justify-end pt-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showTemplateModal', false)" class="cursor-pointer">Annuler</flux:button>
                    <flux:button type="submit" variant="primary" class="cursor-pointer">Enregistrer</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
