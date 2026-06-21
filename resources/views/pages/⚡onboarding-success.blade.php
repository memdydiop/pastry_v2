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
        Votre espace est prêt. Vous pouvez dès maintenant vous connecter
        avec l'email et le mot de passe que vous avez choisis.
    </p>

    <flux:button href="{{ route('home') }}" variant="primary" class="!bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
        Accéder à la page d'accueil
    </flux:button>
</div>
