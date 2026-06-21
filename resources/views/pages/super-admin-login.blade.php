<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <title>Super Admin — {{ config('app.name') }}</title>
    </head>
    <body class="min-h-screen bg-neutral-950 antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium">
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-amber-400" />
                    </span>
                </a>
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-6">
                        <x-auth-header title="Administration" description="Espace réservé aux super-administrateurs." />

                        @if ($errors->any())
                            <flux:callout variant="danger" icon="x-circle" heading="{{ $errors->first('email') }}" />
                        @endif

                        <form method="POST" action="{{ route('super-admin.login') }}" class="flex flex-col gap-6">
                            @csrf

                            <flux:input name="email" label="Email" type="email" required autofocus autocomplete="email" placeholder="admin@exemple.com" />
                            <div class="relative">
                                <flux:input name="password" label="Mot de passe" type="password" required autocomplete="current-password" placeholder="Mot de passe" viewable />
                            </div>
                            <flux:checkbox name="remember" label="Se souvenir de moi" />

                            <flux:button variant="primary" type="submit" class="w-full">
                                Connexion
                            </flux:button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
