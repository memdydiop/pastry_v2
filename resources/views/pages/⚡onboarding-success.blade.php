<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Compte créé')] class extends Component {

}; ?>

<div class="flex flex-col items-center gap-6 text-center">
    <div class="flex size-16 items-center justify-center rounded-full bg-amber-400/10 text-amber-400">
        <flux:icon name="check" class="size-8" />
    </div>

    <x-auth-header title="Félicitations !" description="Votre compte a été créé avec succès." />

    <p class="text-sm text-neutral-400">
        Nous préparons votre espace. Vous recevrez un email avec vos identifiants de connexion
        et les instructions pour accéder à votre tableau de bord.
    </p>

    <flux:button href="https://{{ \Illuminate\Support\Str::slug(request()->session()->get('onboarding_company', '')) }}.{{ config('app.central_domain') }}/login"
        variant="primary" class="!bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
        Accéder à mon espace
    </flux:button>
</div>
