<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\CakeShape;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

new class extends Component {
    use WithFileUploads;

    public $showModal = false;
    public $editingOrderId = null;

    public $client_id = '';

    public $contact_phone_2 = '';
    public $contact_phone_3 = '';

    public $cake_type = '';
    public $tiers_count = 1;
    public $servings_count = '';
    public $flavors_details = '';
    public $decorations_details = '';
    public $theme_description = '';
    public $colors_requested = '';
    public $inscription_text = '';

    public $delivery_date = '';
    public $delivery_time = '12:00';
    public $delivery_address = '';
    public $conservation_notes = '';
    public $allergens = '';
    public $notes = '';

    public $total_amount = 0;
    public $payment_method = 'Espèces';
    public $status = 'En attente';

    public $levels = [];
    public $new_images = [];
    public $existingImages = [];

    #[On('open-order-modal')]
    public function openModal($id = null)
    {
        $this->resetErrorBag();
        $this->editingOrderId = $id;

        if ($id) {
            $order = Order::with(['levels', 'images'])->findOrFail($id);

            $this->client_id = $order->client_id;
            $this->contact_phone_2 = $order->contact_phone_2;
            $this->contact_phone_3 = $order->contact_phone_3;
            $this->cake_type = $order->cake_type;
            $this->tiers_count = $order->tiers_count;
            $this->servings_count = $order->servings_count;
            $this->flavors_details = $order->flavors_details;
            $this->decorations_details = $order->decorations_details;
            $this->theme_description = $order->theme_description;
            $this->colors_requested = $order->colors_requested;
            $this->inscription_text = $order->inscription_text;
            $this->delivery_date = $order->delivery_due_at?->format('Y-m-d') ?: date('Y-m-d');
            $this->delivery_time = $order->delivery_due_at?->format('H:i') ?: '12:00';
            $this->delivery_address = $order->delivery_address;
            $this->conservation_notes = $order->conservation_notes;
            $this->allergens = $order->allergens;
            $this->notes = $order->notes;
            $this->total_amount = floatval($order->total_amount);
            $this->status = $order->status;

            $this->levels = $order->levels->map(fn($l) => [
                'level_number' => $l->level_number,
                'shape' => $l->shape ?? 'Rond',
                'flavor_biscuit' => $l->flavor_biscuit,
                'flavor_cream' => $l->flavor_cream,
                'filling' => $l->filling,
                'diameter_cm' => $l->diameter_cm,
                'width_cm' => $l->width_cm,
                'length_cm' => $l->length_cm,
                'height_cm' => $l->height_cm,
                'notes' => $l->notes,
            ])->toArray();

            $this->existingImages = $order->images;
        } else {
            $this->reset([
                'client_id', 'contact_phone_2', 'contact_phone_3',
                'cake_type',
                'servings_count', 'flavors_details', 'decorations_details',
                'theme_description', 'colors_requested', 'inscription_text',
                'delivery_address', 'conservation_notes', 'allergens',
                'notes', 'total_amount', 'levels', 'new_images', 'existingImages',
            ]);

            $this->delivery_date = date('Y-m-d');
            $this->delivery_time = '12:00';
            $this->tiers_count = 1;
            $this->payment_method = 'Espèces';
            $this->status = 'En attente';
            $this->syncLevels();
        }

        $this->showModal = true;
    }

    public function updatedTiersCount($value)
    {
        $this->syncLevels();
    }

    protected function syncLevels()
    {
        $target = max(1, intval($this->tiers_count));
        $current = count($this->levels);

        if ($target > $current) {
            for ($i = $current; $i < $target; $i++) {
                $this->levels[] = [
                    'level_number' => $i + 1,
                    'shape' => 'Rond',
                    'flavor_biscuit' => '',
                    'flavor_cream' => '',
                    'filling' => '',
                    'diameter_cm' => '',
                    'width_cm' => '',
                    'length_cm' => '',
                    'height_cm' => '',
                    'notes' => '',
                ];
            }
        } elseif ($target < $current) {
            $this->levels = array_slice($this->levels, 0, $target);
        }

        foreach ($this->levels as $i => &$level) {
            $level['level_number'] = $i + 1;
        }
    }

    #[On('client-saved')]
    public function handleClientCreated($clientId)
    {
        $this->client_id = $clientId;
    }

    public function removeImage($imageId)
    {
        $img = \App\Models\OrderImage::find($imageId);
        if ($img) {
            Storage::disk('public')->delete($img->file_path);
            $img->delete();
            $this->existingImages = $this->existingImages->filter(fn($i) => $i->id !== $imageId);
        }
    }

    public function saveOrder()
    {
        $this->validate([
            'client_id' => 'required|exists:clients,id',
            'contact_phone_2' => 'nullable|string|max:20',
            'contact_phone_3' => 'nullable|string|max:20',
            'cake_type' => 'nullable|string|max:255',
            'tiers_count' => 'required|integer|min:1',
            'servings_count' => 'nullable|integer|min:1',
            'flavors_details' => 'nullable|string',
            'decorations_details' => 'nullable|string',
            'theme_description' => 'nullable|string',
            'colors_requested' => 'nullable|string',
            'inscription_text' => 'nullable|string',
            'delivery_date' => 'required|date|after_or_equal:today',
            'delivery_time' => 'required',
            'delivery_address' => 'nullable|string',
            'conservation_notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric|min:0',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'allergens' => 'nullable|string',
            'new_images.*' => 'image|mimes:jpeg,png,webp|max:5120',
            'levels.*.level_number' => 'required|integer|min:1',
            'levels.*.flavor_biscuit' => 'nullable|string|max:255',
            'levels.*.flavor_cream' => 'nullable|string|max:255',
            'levels.*.filling' => 'nullable|string|max:255',
            'levels.*.shape' => ['nullable', Rule::enum(\App\Enums\CakeShape::class)],
            'levels.*.diameter_cm' => 'nullable|numeric|min:0',
            'levels.*.width_cm' => 'nullable|numeric|min:0',
            'levels.*.length_cm' => 'nullable|numeric|min:0',
            'levels.*.height_cm' => 'nullable|numeric|min:0',
            'levels.*.notes' => 'nullable|string|max:500',
        ]);

        $delivery_due_at = $this->delivery_date . ' ' . $this->delivery_time;

        DB::transaction(function () use ($delivery_due_at) {
            $data = [
                'client_id' => $this->client_id,
                'contact_phone_2' => $this->contact_phone_2 ?: null,
                'contact_phone_3' => $this->contact_phone_3 ?: null,
                'cake_type' => $this->cake_type,
                'tiers_count' => $this->tiers_count,
                'servings_count' => $this->servings_count ?: null,
                'flavors_details' => $this->flavors_details,
                'decorations_details' => $this->decorations_details,
                'theme_description' => $this->theme_description,
                'colors_requested' => $this->colors_requested,
                'inscription_text' => $this->inscription_text,
                'delivery_due_at' => $delivery_due_at,
                'delivery_address' => $this->delivery_address,
                'conservation_notes' => $this->conservation_notes,
                'allergens' => $this->allergens ?: null,
                'notes' => $this->notes,
                'total_amount' => $this->total_amount,
                'status' => $this->status,
            ];

            if ($this->editingOrderId) {
                $order = Order::findOrFail($this->editingOrderId);
                $order->update($data);

                $order->levels()->delete();
                $msg = 'Commande mise à jour';
            } else {
                $order = Order::create($data);
                $msg = 'Commande enregistrée';
            }

            foreach ($this->levels as $levelData) {
                $levelData['diameter_cm'] = $levelData['diameter_cm'] !== '' ? $levelData['diameter_cm'] : null;
                $levelData['width_cm'] = $levelData['width_cm'] !== '' ? $levelData['width_cm'] : null;
                $levelData['length_cm'] = $levelData['length_cm'] !== '' ? $levelData['length_cm'] : null;
                $levelData['height_cm'] = $levelData['height_cm'] !== '' ? $levelData['height_cm'] : null;
                $order->levels()->create($levelData);
            }

            foreach ($this->new_images as $img) {
                $path = $img->store('order-images', 'public');
                $order->images()->create([
                    'file_path' => $path,
                    'original_name' => $img->getClientOriginalName(),
                    'mime_type' => $img->getClientMimeType(),
                    'size' => $img->getSize(),
                ]);
            }

            $this->dispatch('toast', variant: 'success', heading: $msg);
        });

        $this->dispatch('order-saved');
        $this->showModal = false;
    }
}; ?>

<div>
    <flux:modal name="order-form-modal" wire:model="showModal" class="w-full max-w-5xl space-y-6" flyout>
        <form wire:submit.prevent="saveOrder" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingOrderId ? 'Modifier la commande' : 'Prendre une commande' }}</flux:heading>
                <flux:subheading>Fiche technique et comptable pour la fabrication du sur-mesure.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="md:col-span-2 space-y-5">
                    <div class="flex items-end gap-2 bg-zinc-50 dark:bg-zinc-950/20 p-4 rounded-xl border border-zinc-200/60 dark:border-zinc-800/50">
                        <div class="flex-1">
                            <flux:select wire:model.live="client_id" label="Sélectionner le Client" placeholder="Rechercher..." required>
                                @foreach(Client::orderBy('name')->get() as $client)
                                    <flux:select.option value="{{ $client->id }}">
                                        {{ $client->name }} — {{ $client->phone }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        @if(!$editingOrderId)
                            <flux:button icon="plus" variant="subtle" class="cursor-pointer" x-on:click="$flux.modal('client-form-modal').show()" title="Créer un client" />
                        @endif
                    </div>

                    <div class="bg-zinc-50 dark:bg-zinc-950/20 p-4 rounded-xl border border-zinc-200/60 dark:border-zinc-800/50">
                        <flux:heading size="sm" class="uppercase tracking-wider text-zinc-400 font-bold text-[11px]">Personnes à contacter</flux:heading>
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <flux:input wire:model="contact_phone_2" label="Téléphone alternatif 1" placeholder="Optionnel" />
                            <flux:input wire:model="contact_phone_3" label="Téléphone alternatif 2" placeholder="Optionnel" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <flux:input wire:model="cake_type" label="Type de gâteau" placeholder="Ex: Mariage" />
                        <flux:input type="number" wire:model.live="tiers_count" label="Étages" min="1" required />
                        <flux:input type="number" wire:model="servings_count" label="Parts" placeholder="Ex: 25" />
                        <flux:input type="number" wire:model.live="total_amount" label="Prix de la pièce" required />
                    </div>

                    <div class="space-y-3">
                        <flux:input wire:model="inscription_text" label="Inscription" placeholder="Texte sur le gâteau" />
                    </div>

                    <div class="space-y-3">
                        <flux:textarea wire:model="flavors_details" label="Saveurs & Garnitures"
                            placeholder="Ex: Génoise vanille, ganache chocolat, crème mousseline..." rows="2" />
                        <flux:textarea wire:model="decorations_details" label="Design & Décoration"
                            placeholder="Ex: Effet marbre, fleurs en sucre, dorure à la feuille..." rows="2" />
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <flux:textarea wire:model="theme_description" label="Thème & Style"
                                placeholder="Ex: Bohème chic, couleur nude et blush..." rows="2" />
                            <flux:textarea wire:model="colors_requested" label="Couleurs demandées"
                                placeholder="Ex: Blanc cassé, rose poudré, or..." rows="2" />
                        </div>
                        <flux:textarea wire:model="allergens" label="Allergènes"
                            placeholder="Ex: Fruits à coque, lactose, gluten..." rows="1" />
                    </div>

                    @if(count($levels) > 0)
                        <div class="bg-zinc-50 dark:bg-zinc-950/20 p-4 rounded-xl border border-zinc-200/60 dark:border-zinc-800/50 space-y-4">
                            <flux:heading size="sm" class="uppercase tracking-wider text-zinc-400 font-bold text-[11px]">
                                Détail des Étages
                            </flux:heading>

                            @foreach($levels as $index => $level)
                                <div class="border border-zinc-200/60 dark:border-zinc-800/60 rounded-lg p-3 bg-white dark:bg-zinc-900/50">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-bold text-zinc-700 dark:text-zinc-300">
                                            Étage {{ $level['level_number'] }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <flux:input wire:model="levels.{{ $index }}.flavor_biscuit" label="Biscuit" size="sm" placeholder="Ex: Vanille" />
                                        <flux:input wire:model="levels.{{ $index }}.flavor_cream" label="Crème" size="sm" placeholder="Ex: Chocolat" />
                                        <flux:input wire:model="levels.{{ $index }}.filling" label="Garniture" size="sm" placeholder="Ex: Fruits rouges" />
                                    </div>
                                    <div class="grid grid-cols-4 gap-2 mt-2">
                                        <flux:select wire:model.live="levels.{{ $index }}.shape" label="Forme" size="sm">
                                            @foreach(CakeShape::cases() as $shape)
                                                <option value="{{ $shape->value }}">{{ $shape->label() }}</option>
                                            @endforeach
                                        </flux:select>
                                        
                                        <flux:input type="number" wire:model="levels.{{ $index }}.height_cm" label="H (cm)" size="sm" step="0.1" />

                                        @if(($level['shape'] ?? 'Rond') === 'Rond')
                                            <flux:input type="number" wire:model="levels.{{ $index }}.diameter_cm" label="Ø (cm)" size="sm" step="0.1" />
                                        @else
                                            <flux:input type="number" wire:model="levels.{{ $index }}.width_cm" label="Larg. (cm)" size="sm" step="0.1" />
                                        @endif
                                        
                                        @if(($level['shape'] ?? '') === 'Rectangle')
                                            <flux:input type="number" wire:model="levels.{{ $index }}.length_cm" label="Long. (cm)" size="sm" step="0.1" />
                                        @endif
                                        <div class="col-span-4">
                                            <flux:input wire:model="levels.{{ $index }}.notes" label="Notes" size="sm" placeholder="Spécificités..." />
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="bg-zinc-50 dark:bg-zinc-950/20 p-4 rounded-xl border border-zinc-200/60 dark:border-zinc-800/50 space-y-3">
                        <flux:heading size="sm" class="uppercase tracking-wider text-zinc-400 font-bold text-[11px]">
                            Images & Croquis
                        </flux:heading>

                        @if(count($existingImages) > 0)
                            <div class="flex flex-wrap gap-2">
                                @foreach($existingImages as $img)
                                    <div class="relative group">
                                        <img src="{{ Storage::url($img->file_path) }}" alt="{{ $img->original_name }}"
                                            class="size-16 object-cover rounded-lg border border-zinc-200 dark:border-zinc-700" />
                                        <button type="button" wire:click="removeImage({{ $img->id }})"
                                            class="absolute -top-1.5 -right-1.5 size-5 bg-rose-500 text-white rounded-full text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">✕</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <flux:input type="file" wire:model="new_images" label="Ajouter des images" multiple accept="image/*" />
                    </div>
                </div>

                <div class="space-y-4 bg-zinc-50 dark:bg-zinc-950/40 p-4 rounded-xl border border-zinc-200/60 dark:border-zinc-800/60 flex flex-col justify-between">
                    <div class="space-y-4">
                        <flux:heading size="sm" class="uppercase tracking-wider text-zinc-400 font-bold text-[11px]">Logistique Labo</flux:heading>

                        <div class="grid grid-cols-3 gap-x-2">
                            <div class="col-span-3">
                                <flux:label >Date et Heure de retrait</flux:label>
                            </div>
                            <div class="col-span-2"> 
                                <flux:input type="date" wire:model="delivery_date" required size="sm" />
                            </div>
                            <div class="col-span-1">
                                <flux:input type="time" wire:model="delivery_time" required size="sm" />
                            </div>
                        </div>

                        <flux:textarea wire:model="delivery_address" label="Adresse livraison"
                            placeholder="Adresse de retrait ou livraison..." rows="2" size="sm" />
                        <flux:textarea wire:model="conservation_notes" label="Conservation"
                            placeholder="Ex: À conserver au frais..." rows="1" size="sm" />

                        <flux:select wire:model="status" label="Statut" size="sm">
                            @foreach(App\Enums\OrderStatus::cases() as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </flux:select>

                        <flux:textarea wire:model="notes" label="Consignes" placeholder="Consignes spéciales..." rows="2" size="sm" />
                    </div>
                </div>
            </div>

            <div class="flex gap-2 justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer" icon="check">
                    {{ $editingOrderId ? 'Enregistrer les modifications' : 'Valider la commande' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
