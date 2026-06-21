<?php

use App\Models\Plan;
use App\Services\CinetPayService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Créer mon compte')] class extends Component {
    public int $step = 1;

    public string $company_name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    public ?int $selectedPlanId = null;
    public string $paymentUrl = '';

    protected $listeners = ['planSelected' => 'selectPlan'];

    public function mount(): void
    {
        $planId = request('plan');
        if ($planId && $plan = Plan::find($planId)) {
            $this->selectedPlanId = $plan->id;
        }
    }

    public function selectPlan(int $planId): void
    {
        $this->selectedPlanId = $planId;
    }

    public function submitCompany(): void
    {
        $this->validate([
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $this->step = 2;
    }

    public function submitPlan(): void
    {
        $this->validate([
            'selectedPlanId' => 'required|exists:plans,id',
        ]);

        $this->step = 3;
    }

    public function processPayment(): void
    {
        $plan = Plan::findOrFail($this->selectedPlanId);

        if ($plan->price <= 0) {
            $this->createTenant($plan);
            return;
        }

        $transactionId = (string) \Illuminate\Support\Str::uuid();

        Session::put("onboarding.{$transactionId}", [
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'plan_id' => $plan->id,
            'amount' => $plan->price,
        ]);

        try {
            $service = app(CinetPayService::class);
            $result = $service->generatePaymentLink([
                'transaction_id' => $transactionId,
                'amount' => $plan->price,
                'description' => "Abonnement {$plan->name} - {$this->company_name}",
                'customer_name' => $this->company_name,
                'customer_email' => $this->email,
                'customer_phone_number' => $this->phone,
                'return_url' => route('onboarding.success'),
            ]);

            $this->paymentUrl = $result['data']['payment_url'] ?? '';
        } catch (\Exception $e) {
            $this->addError('payment', $e->getMessage());
        }
    }

    public function createTenant(Plan $plan): void
    {
        $tenantId = (string) \Illuminate\Support\Str::uuid();

        $tenant = \App\Models\Tenant::create([
            'id' => $tenantId,
            'data' => [
                'company_name' => $this->company_name,
                'email' => $this->email,
                'phone' => $this->phone,
            ],
        ]);

        $tenant->domains()->create([
            'domain' => \Illuminate\Support\Str::slug($this->company_name) . '.' . config('app.central_domain'),
            'is_primary' => true,
        ]);

        $tenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $this->redirect(route('onboarding.success'));
    }

    public function getPlansProperty()
    {
        return Plan::all();
    }

    public function getSelectedPlanProperty()
    {
        return $this->selectedPlanId ? Plan::find($this->selectedPlanId) : null;
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Créer votre compte" description="Configurez votre espace pâtisserie en quelques étapes." />

    <div class="flex items-center gap-2">
        @for ($i = 1; $i <= 3; $i++)
            <div class="flex-1 h-1 rounded-full @if($i <= $step) bg-amber-400 @else bg-white/10 @endif"></div>
        @endfor
    </div>

    @if ($step === 1)
        <form wire:submit="submitCompany" class="flex flex-col gap-6">
            <flux:input wire:model="company_name" label="Nom de l'entreprise" placeholder="Pâtisserie ..." required />
            <flux:input wire:model="email" label="Email" type="email" placeholder="contact@exemple.com" required />
            <flux:input wire:model="phone" label="Téléphone" type="tel" placeholder="+225 01 02 03 04 05" required />
            <flux:input wire:model="password" label="Mot de passe" type="password" viewable required placeholder="8 caractères minimum" autocomplete="new-password" />
            <flux:input wire:model="password_confirmation" label="Confirmer le mot de passe" type="password" viewable required autocomplete="new-password" />

            <flux:button type="submit" variant="primary" class="w-full !bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
                Continuer
            </flux:button>
        </form>

    @elseif ($step === 2)
        <div class="flex flex-col gap-6">
            <p class="text-sm text-neutral-400">Choisissez le plan adapté à votre activité.</p>

            <div class="grid gap-4">
                @foreach ($this->plans as $plan)
                    <button wire:click="selectPlan({{ $plan->id }})" type="button"
                        class="flex items-center gap-4 rounded-xl border p-4 text-left transition-all cursor-pointer
                        @if($selectedPlanId === $plan->id) border-amber-400 bg-amber-400/5 @else border-white/10 bg-white/[0.03] hover:border-white/20 @endif">
                        <div class="flex size-5 shrink-0 items-center justify-center rounded-full border-2 @if($selectedPlanId === $plan->id) border-amber-400 @else border-white/20 @endif">
                            @if($selectedPlanId === $plan->id)
                                <div class="size-2.5 rounded-full bg-amber-400"></div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <div class="font-semibold">{{ $plan->name }}</div>
                            <div class="text-sm text-neutral-400">{{ $plan->description }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold">{{ number_format($plan->price, 0, ',', ' ') }}</div>
                            <div class="text-xs text-neutral-400">XOF/mois</div>
                        </div>
                    </button>
                @endforeach
            </div>

            @error('selectedPlanId') <p class="text-sm text-red-400">{{ $message }}</p> @enderror

            <div class="flex gap-3">
                <flux:button wire:click="$set('step', 1)" variant="ghost" class="flex-1">
                    Retour
                </flux:button>
                <flux:button wire:click="submitPlan" variant="primary" class="flex-1 !bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
                    Continuer
                </flux:button>
            </div>
        </div>

    @elseif ($step === 3)
        <div class="flex flex-col gap-6">
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-neutral-400">Plan</span>
                    <span class="font-semibold">{{ $this->selectedPlan?->name }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between text-sm">
                    <span class="text-neutral-400">Montant</span>
                    <span class="text-lg font-bold text-amber-400">{{ $this->selectedPlan ? number_format($this->selectedPlan->price, 0, ',', ' ') : '' }} XOF</span>
                </div>
            </div>

            @if ($this->selectedPlan && $this->selectedPlan->price > 0)
                <p class="text-sm text-neutral-400">
                    Vous allez être redirigé vers CinetPay pour effectuer le paiement.
                    Paiement accepté : Wave, Orange Money, MTN MoMo.
                </p>

                @error('payment') <p class="text-sm text-red-400">{{ $message }}</p> @enderror

                <flux:button wire:click="processPayment" variant="primary" class="w-full !bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
                    Payer avec CinetPay
                </flux:button>

                @if ($paymentUrl)
                    <script>
                        window.location.href = "{{ $paymentUrl }}";
                    </script>
                @endif
            @else
                <flux:button wire:click="processPayment" variant="primary" class="w-full !bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
                    Créer mon compte gratuit
                </flux:button>
            @endif

            <flux:button wire:click="$set('step', 2)" variant="ghost" class="w-full">
                Modifier le plan
            </flux:button>
        </div>
    @endif
</div>
