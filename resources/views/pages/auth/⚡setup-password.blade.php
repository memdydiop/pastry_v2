<?php

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Définir mon mot de passe')] class extends Component {
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->token = request('token', '');
        $this->email = request('email', '');
    }

    public function save(): void
    {
        $this->validate([
            'token' => 'required|string|size:64',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::where('email', $this->email)
            ->where('setup_token', $this->token)
            ->whereNull('setup_completed_at')
            ->first();

        if (!$user) {
            $this->addError('email', 'Lien invalide ou expiré.');
            return;
        }

        $expiry = (int) Setting::getValue('invitation_expiry_time', INVITATION_EXPIRY_TIME);

        if ($user->setup_token_sent_at && $user->setup_token_sent_at->addMinutes($expiry)->isPast()) {
            $label = $expiry >= 60 ? ($expiry / 60) . 'h' : $expiry . 'min';
            $this->addError('email', 'Ce lien a expiré (' . $label . '). Veuillez contacter l\'administration pour recevoir une nouvelle invitation.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->password),
            'setup_token' => null,
            'setup_completed_at' => Carbon::now(),
            'is_active' => true,
        ]);

        $this->redirect(route('login'), navigate: true);
        session()->flash('status', 'Compte activé. Vous pouvez maintenant vous connecter.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Bienvenue !" description="Définissez votre mot de passe pour activer votre compte." />

    @if (session('status'))
        <x-auth-session-status class="text-center" :status="session('status')" />
    @endif

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input
            wire:model="email"
            label="Email"
            type="email"
            disabled
        />

        <flux:input
            wire:model="password"
            label="Mot de passe"
            type="password"
            required
            autocomplete="new-password"
            placeholder="8 caractères minimum"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            label="Confirmer le mot de passe"
            type="password"
            required
            autocomplete="new-password"
            placeholder="Répétez le mot de passe"
            viewable
        />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Activer mon compte') }}
            </flux:button>
        </div>
    </form>
</div>
