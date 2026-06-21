<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;

new #[Title('Détail Commande')] class extends Component {
    public Order $order;

    public $showCancelModal = false;
    public $showDeleteModal = false;
    public $cancellationReason = '';
    public $cancelErrors = [];

    public function mount(Order $order)
    {
        $this->order = $order->load([
            'levels.recipe.recipeIngredients.ingredient',
            'images',
            'statusLogs.user',
            'transactions',
            'client',
        ]);
    }

    public function prepareDelete()
    {
        $this->showDeleteModal = true;
    }

    public function confirmDelete()
    {
        $this->authorize('gerantOrGhost');

        $this->order->transactions()->delete();
        $this->order->levels()->delete();
        $this->order->statusLogs()->delete();
        $this->order->images()->delete();
        $this->order->delete();

        $this->dispatch('toast', variant: 'success', heading: 'Commande supprimée.');
        $this->redirect(route('orders.index'), navigate: true);
    }

    public function openCancelModal()
    {
        $this->cancelErrors = $this->order->canBeCancelled();

        if (auth()->user()->cannot('gerantOrGhost') && !auth()->user()->hasRole('Chef Pâtissier')) {
            $this->cancelErrors[] = 'Seuls les administrateurs et chefs pâtissiers peuvent annuler une commande.';
        }

        if (!empty($this->cancelErrors)) {
            return;
        }

        $this->cancellationReason = '';
        $this->showCancelModal = true;
    }

    public function processCancel()
    {
        $this->authorize('gerantOrGhost');

        $this->validate([
            'cancellationReason' => 'required|string|min:3|max:1000',
        ]);

        $this->cancelErrors = $this->order->canBeCancelled();
        if (!empty($this->cancelErrors)) {
            return;
        }

        try {
            $this->order->cancel($this->cancellationReason);
            $this->order->refresh();
            $this->dispatch('toast', variant: 'success', heading: 'Commande annulée.', description: 'Les remboursements ont été créés automatiquement.');
            $this->showCancelModal = false;
        } catch (\RuntimeException $e) {
            $this->cancelErrors = [$e->getMessage()];
        }
    }

    public function with(): array
    {
        return [
            'totalPaid' => $this->order->total_paid,
            'remaining' => $this->order->remaining_balance,
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Commande ' . $order->reference"
        :subtitle="($order->client->name ?? '') . ' — ' . $order->cake_type">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>Commandes</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $order->reference }}</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button icon="arrow-left" variant="subtle" :href="route('orders.index')" wire:navigate>
                Retour
            </flux:button>
        </x-slot:actions>
    </x-page-heading>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                <x-card.card-header title="Détails de la Commande" subtitle="Informations générales" />
                <x-card.card-body class="p-5 space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Client</div>
                            <div class="text-sm font-medium mt-1">{{ $order->client->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $order->client->phone }}</div>
                            @if($order->contact_phone_2)
                                <div class="text-xs text-zinc-400">Alt 1: {{ $order->contact_phone_2 }}</div>
                            @endif
                            @if($order->contact_phone_3)
                                <div class="text-xs text-zinc-400">Alt 2: {{ $order->contact_phone_3 }}</div>
                            @endif
                        </div>
                        <div>
                            <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Type</div>
                            <div class="text-sm font-medium mt-1">{{ $order->cake_type ?: 'Gâteau classique' }}</div>
                            @if($order->tiers_count > 1)
                                <div class="text-xs text-indigo-500">{{ $order->tiers_count }} étages</div>
                            @endif
                        </div>
                        <div>
                            <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Statut</div>
                            <div class="mt-1">
                                <flux:badge :variant="$order->status?->badgeVariant() ?? 'neutral'" size="sm">
                                    {{ $order->status?->label() ?? $order->status }}
                                </flux:badge>
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Livraison</div>
                            <div class="text-sm font-medium mt-1">{{ $order->delivery_due_at?->format('d/m/Y') ?: '—' }}</div>
                            <div class="text-xs text-zinc-500">à {{ $order->delivery_due_at?->format('H:i') ?: '—' }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        <div>
                            <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Parts</div>
                            <div class="text-sm font-medium mt-1">{{ $order->servings_count ?: 'Non spécifié' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Inscription</div>
                            <div class="text-sm font-medium mt-1">{{ $order->inscription_text ?: '—' }}</div>
                        </div>
                    </div>

                    @if($order->flavors_details || $order->decorations_details || $order->theme_description || $order->colors_requested)
                        <div class="grid grid-cols-2 gap-4 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                            @if($order->flavors_details)
                                <div>
                                    <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Saveurs</div>
                                    <div class="text-sm mt-1">{{ $order->flavors_details }}</div>
                                </div>
                            @endif
                            @if($order->decorations_details)
                                <div>
                                    <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Décoration</div>
                                    <div class="text-sm mt-1">{{ $order->decorations_details }}</div>
                                </div>
                            @endif
                            @if($order->theme_description)
                                <div>
                                    <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Thème</div>
                                    <div class="text-sm mt-1">{{ $order->theme_description }}</div>
                                </div>
                            @endif
                            @if($order->colors_requested)
                                <div>
                                    <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Couleurs</div>
                                    <div class="text-sm mt-1">{{ $order->colors_requested }}</div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($order->delivery_address || $order->conservation_notes || $order->allergens)
                        <div class="space-y-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                            <div class="grid grid-cols-2 gap-4">
                                @if($order->delivery_address)
                                    <div>
                                        <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Adresse</div>
                                        <div class="text-sm mt-1">{{ $order->delivery_address }}</div>
                                    </div>
                                @endif
                                @if($order->conservation_notes)
                                    <div>
                                        <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Conservation</div>
                                        <div class="text-sm mt-1">{{ $order->conservation_notes }}</div>
                                    </div>
                                @endif
                                @if($order->allergens)
                                    <div>
                                        <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Allergènes</div>
                                        <div class="text-sm mt-1">{{ $order->allergens }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($order->notes)
                        <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800">
                            <div class="text-xs text-zinc-400 uppercase font-semibold tracking-wider">Consignes</div>
                            <div class="text-sm mt-1">{{ $order->notes }}</div>
                        </div>
                    @endif
                </x-card.card-body>
            </flux:card>

            @if($order->levels->count() > 0)
                <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                    <x-card.card-header title="Étages" :subtitle="$order->levels->count() . ' niveau(x)'" />
                    <x-card.card-body class="p-5 space-y-3">
                        @foreach($order->levels as $level)
                            <div class="border border-zinc-200/60 dark:border-zinc-800/60 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-950/20">
                                <div class="text-sm font-bold text-zinc-700 dark:text-zinc-300 mb-2">
                                    Étage {{ $level->level_number }}
                                    @if($level->recipe)
                                        <flux:badge size="sm" variant="indigo" class="ml-2 px-1.5 py-0.5">{{ $level->recipe->name }}</flux:badge>
                                    @endif
                                </div>
                                <div class="grid grid-cols-3 gap-4 text-xs">
                                    <div><span class="text-zinc-400">Biscuit :</span> <span class="font-medium">{{ $level->flavor_biscuit ?: '—' }}</span></div>
                                    <div><span class="text-zinc-400">Crème :</span> <span class="font-medium">{{ $level->flavor_cream ?: '—' }}</span></div>
                                    <div><span class="text-zinc-400">Garniture :</span> <span class="font-medium">{{ $level->filling ?: '—' }}</span></div>
                                    <div><span class="text-zinc-400">Diamètre :</span> <span class="font-medium">{{ $level->diameter_cm ? $level->diameter_cm . ' cm' : '—' }}</span></div>
                                    <div><span class="text-zinc-400">Hauteur :</span> <span class="font-medium">{{ $level->height_cm ? $level->height_cm . ' cm' : '—' }}</span></div>
                                    @if($level->notes)
                                        <div class="col-span-3"><span class="text-zinc-400">Notes :</span> <span class="font-medium">{{ $level->notes }}</span></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-card.card-body>
                </flux:card>
            @endif

            @if($order->images->count() > 0)
                <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                    <x-card.card-header title="Images & Croquis" :subtitle="$order->images->count() . ' fichier(s)'" />
                    <x-card.card-body class="p-5">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach($order->images as $img)
                                <a href="{{ Storage::url($img->file_path) }}" target="_blank" class="block">
                                    <img src="{{ Storage::url($img->file_path) }}" alt="{{ $img->original_name }}"
                                        class="w-full h-28 object-cover rounded-lg border border-zinc-200 dark:border-zinc-700 hover:opacity-80 transition-opacity" />
                                    <div class="text-[10px] text-zinc-400 mt-1 truncate">{{ $img->original_name }}</div>
                                </a>
                            @endforeach
                        </div>
                    </x-card.card-body>
                </flux:card>
            @endif

            @if($order->statusLogs->count() > 0)
                <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                    <x-card.card-header title="Historique des Statuts" subtitle="Traçabilité complète" />
                    <x-card.card-body class="p-5">
                        <ol class="relative border-s border-zinc-200 dark:border-zinc-700 ms-2 space-y-3">
                            @foreach($order->statusLogs->sortByDesc('created_at') as $log)
                                <li class="ms-6">
                                    <span class="absolute flex items-center justify-center w-5 h-5 bg-white dark:bg-zinc-900 rounded-full -start-[10px] ring-2 ring-zinc-200 dark:ring-zinc-700">
                                        <flux:icon.arrow-path variant="micro" class="text-zinc-400 size-3" />
                                    </span>
                                    <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $log->from_status?->label() ?? $log->from_status }}
                                        <flux:icon.chevron-right variant="micro" class="inline text-zinc-400 size-3" />
                                        {{ $log->to_status?->label() ?? $log->to_status }}
                                    </div>
                                    <div class="text-xs text-zinc-400">
                                        {{ $log->user?->name ?: 'Système' }} — {{ $log->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </x-card.card-body>
                </flux:card>
            @endif
        </div>

        <div class="space-y-6">
            <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                <x-card.card-header title="Actions Rapides" />
                <x-card.card-body class="p-4 space-y-2">
                    @php $whatsappLink = \App\Helpers\WhatsApp::linkForOrder($order, 'order_contact'); @endphp
                    @if($whatsappLink)
                        <flux:button icon="chat-bubble-left" variant="primary" class="w-full cursor-pointer"
                            :href="$whatsappLink"
                            target="_blank">
                            WhatsApp {{ $order->client->name }}
                        </flux:button>
                    @endif

                    <flux:button icon="pencil-square" variant="subtle" class="w-full cursor-pointer"
                        wire:click="$dispatch('open-order-modal', { id: {{ $order->id }} })"
                        x-on:click="$flux.modal('order-form-modal').show()">
                        Modifier la commande
                    </flux:button>

                    @if(auth()->user()->hasAnyRole(['ghost', 'Gérant/Admin', 'Chef Pâtissier']) && !$order->isCancelled() && $order->status?->value !== 'Livrée')
                        <flux:button icon="x-circle" variant="danger" class="w-full cursor-pointer"
                            wire:click="openCancelModal">
                            Annuler la commande
                        </flux:button>
                    @endif

                    <flux:button icon="trash" variant="danger" class="w-full cursor-pointer"
                        wire:click="prepareDelete">
                        Supprimer
                    </flux:button>
                </x-card.card-body>
            </flux:card>

            @php
                $stockMovements = $order->levels->flatMap(fn($l) => $l->recipe?->recipeIngredients ?? []);
            @endphp

            @if($stockMovements->isNotEmpty())
                <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                    <x-card.card-header title="Ingrédients (Recettes)" subtitle="Basé sur les fiches techniques" />
                    <x-card.card-body class="p-4 space-y-2">
                        @foreach($stockMovements->groupBy(fn($ri) => $ri->ingredient->name) as $name => $items)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $name }}</span>
                                <span class="font-medium text-zinc-900 dark:text-white">
                                    {{ number_format($items->sum(fn($ri) => $ri->quantity), 2, ',', ' ') }}
                                    {{ $items->first()->unit_override ?? $items->first()->ingredient->unit }}
                                </span>
                            </div>
                        @endforeach
                    </x-card.card-body>
                </flux:card>
            @endif

            <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                <x-card.card-header title="Résumé Financier" subtitle="FCFA" />
                <x-card.card-body class="p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-zinc-500">Total commande</span>
                        <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ number_format($order->total_amount, 0, ',', ' ') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-zinc-500">Déjà payé</span>
                        <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($totalPaid, 0, ',', ' ') }}</span>
                    </div>
                    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-3 flex items-center justify-between">
                        <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Reste à percevoir</span>
                        <span class="text-lg font-black text-rose-600 dark:text-rose-400">{{ number_format($remaining, 0, ',', ' ') }}</span>
                    </div>
                </x-card.card-body>
            </flux:card>



            @if($order->transactions->count() > 0)
                <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                    <x-card.card-header title="Transactions" :subtitle="$order->transactions->count() . ' opération(s)'" />
                    <x-card.card-body class="p-0">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Date</flux:table.column>
                                <flux:table.column class="text-right">Montant</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($order->transactions->sortByDesc('paid_at') as $tx)
                                    <flux:table.row>
                                        <flux:table.cell>
                                            <div class="text-xs">{{ $tx->paid_at?->format('d/m/Y H:i') ?: '—' }}</div>
                                            <div class="text-[10px] text-zinc-400">{{ $tx->payment_method?->label() ?? $tx->payment_method }}</div>
                                        </flux:table.cell>
                                        <flux:table.cell class="text-right">
                                            <span class="text-sm font-bold {{ $tx->type->value === 'refund' ? 'text-rose-500' : 'text-emerald-600 dark:text-emerald-400' }}">
                                                @if($tx->type->value === 'refund') -@endif
                                                {{ number_format($tx->amount, 0, ',', ' ') }}
                                            </span>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </x-card.card-body>
                </flux:card>
            @endif
        </div>
    </div>

    <livewire:pages::orders.modals.order-form-modal />
    <livewire:pages::clients.modals.client-form-modal />

    <flux:modal name="delete-order-modal" wire:model.self="showDeleteModal" class="min-w-[22rem]">
        <form wire:submit="confirmDelete" class="space-y-6">
            <div>
                <flux:heading size="lg">Supprimer cette commande ?</flux:heading>
                <flux:text class="mt-2">Toutes les transactions et données liées seront définitivement effacées.</flux:text>
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

    <flux:modal name="cancel-order-modal" wire:model="showCancelModal" class="max-w-lg space-y-6">
        <form wire:submit.prevent="processCancel" class="space-y-6">
            <div>
                <flux:heading size="lg">Annuler la commande</flux:heading>
                <flux:subheading>
                    @if($order->transactions->whereNull('cancelled_at')->where('type', 'payment')->count() > 0)
                        Cette commande a des paiements. Un remboursement sera automatiquement créé pour chaque paiement.
                    @else
                        Cette commande sera marquée comme annulée. Aucun remboursement nécessaire.
                    @endif
                </flux:subheading>
            </div>

            <div class="bg-zinc-50 dark:bg-zinc-950/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-zinc-500">Commande</span>
                    <span class="font-mono text-xs">{{ $order->reference }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-zinc-500">Client</span>
                    <span>{{ $order->client->name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-zinc-500">Statut actuel</span>
                    <flux:badge :variant="$order->status?->badgeVariant() ?? 'neutral'" size="sm">
                        {{ $order->status?->label() ?? $order->status }}
                    </flux:badge>
                </div>
                @if($order->delivery_due_at)
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Livraison prévue</span>
                        <span>{{ $order->delivery_due_at->format('d/m/Y H:i') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm font-semibold border-t border-zinc-200 dark:border-zinc-800 pt-2 mt-2">
                    <span>Total commande</span>
                    <span>{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</span>
                </div>
            </div>

            <flux:textarea wire:model="cancellationReason" label="Motif d'annulation" required
                placeholder="Ex: Client a changé d'avis, problème d'approvisionnement..."
                rows="3" size="sm" />

            <div class="flex gap-2 justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">Retour</flux:button>
                <flux:button type="submit" variant="danger" class="cursor-pointer" icon="x-circle">
                    Confirmer l'annulation
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
