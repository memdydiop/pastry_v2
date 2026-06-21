<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Transaction;
use App\Enums\TransactionType;
use Illuminate\Validation\Rule;

new class extends Component {
    public $showModal = false;
    public $orderId = null;
    public $order = null;

    public $amount = 0;
    public $payment_method = 'Espèces';
    public $paid_at = '';
    public $external_ref = '';
    public $notes = '';

    #[On('open-payment-modal')]
    public function openModal($orderId)
    {
        $this->resetErrorBag();
        $this->reset('amount', 'payment_method', 'external_ref', 'notes');
        $this->orderId = $orderId;
        $this->order = Order::findOrFail($orderId);
        $this->paid_at = now()->format('Y-m-d\TH:i');
        $this->payment_method = 'Espèces';
        $this->showModal = true;
    }

    public function savePayment()
    {
        $this->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_at' => 'required|date',
            'external_ref' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($this->orderId);
        $remaining = floatval($order->total_amount) - floatval($order->total_paid);

        if ($this->amount > $remaining) {
            $this->addError('amount', "Le montant dépasse le solde restant (" . number_format($remaining, 0, ',', ' ') . " FCFA).");
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $order->transactions()->create([
                'reference' => Transaction::generateReference('Paiement'),
                'amount' => $this->amount,
                'payment_method' => $this->payment_method,
                'paid_at' => $this->paid_at,
                'external_ref' => $this->external_ref,
                'notes' => $this->notes,
                'user_id' => auth()->id(),
            ]);

            if ($order->status === OrderStatus::EN_ATTENTE) {
                $order->status = OrderStatus::ACOMPTE_PERÇU;
                $order->save();
            }
        });

        $this->dispatch('toast', variant: 'success', heading: 'Paiement enregistré');
        $this->dispatch('order-saved');
        $this->showModal = false;
    }
}; ?>

<div>
    <flux:modal name="payment-modal" wire:model="showModal" class="max-w-lg space-y-6">
        @if($order)
            <form wire:submit.prevent="savePayment" class="space-y-6">
                <div>
                    <flux:heading size="lg">Enregistrer un paiement</flux:heading>
                    <flux:subheading>Commande {{ $order->reference }} — {{ $order->client->name }}</flux:subheading>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-zinc-50 dark:bg-zinc-950/40 rounded-xl p-3 text-center border border-zinc-200/60 dark:border-zinc-800/60">
                        <flux:text size="xs" class="text-zinc-400 block font-semibold uppercase tracking-wider">Total</flux:text>
                        <div class="text-lg font-black text-zinc-900 dark:text-white mt-1">
                            {{ number_format($order->total_amount, 0, ',', ' ') }} <span class="text-xs">FCFA</span>
                        </div>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-950/40 rounded-xl p-3 text-center border border-zinc-200/60 dark:border-zinc-800/60">
                        <flux:text size="xs" class="text-zinc-400 block font-semibold uppercase tracking-wider">Déjà perçu</flux:text>
                        <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 mt-1">
                            {{ number_format($order->total_paid, 0, ',', ' ') }} <span class="text-xs">FCFA</span>
                        </div>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-950/40 rounded-xl p-3 text-center border border-zinc-200/60 dark:border-zinc-800/60">
                        <flux:text size="xs" class="text-zinc-400 block font-semibold uppercase tracking-wider">Reste</flux:text>
                        <div class="text-lg font-black text-rose-600 dark:text-rose-400 mt-1">
                            {{ number_format(max(0, $order->total_amount - $order->total_paid), 0, ',', ' ') }} <span class="text-xs">FCFA</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <flux:input type="number" wire:model="amount" label="Montant du paiement" placeholder="Ex: 15000" step="1" min="1" required />
                    <flux:select wire:model="payment_method" label="Moyen de paiement" required>
                        @foreach(App\Enums\PaymentMethod::cases() as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input type="datetime-local" wire:model="paid_at" label="Date et heure" required />
                    <flux:input wire:model="external_ref" label="N° Transaction / Mobile" placeholder="Ex: WAVE-123ABC, OM-456789, Reçu 0012..." />
                    <flux:textarea wire:model="notes" label="Notes (optionnel)" placeholder="Ex: Paiement effectué par le client en agence..." rows="2" />
                </div>

                <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                    <flux:button type="submit" variant="primary" class="cursor-pointer" icon="check">
                        Enregistrer le paiement
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
