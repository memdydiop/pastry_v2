<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Tableau de bord') }}
                </flux:sidebar.item>

                <flux:sidebar.group :heading="__('Ventes & Clients')" class="grid">
                    <flux:sidebar.item icon="shopping-bag" :href="route('orders.index')" :current="request()->routeIs('orders.*')" wire:navigate>
                        {{ __('Commandes') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('clients.index')" :current="request()->routeIs('clients.*')" wire:navigate>
                        {{ __('Fiches Clients') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Atelier & Production')" class="grid">
                    <flux:sidebar.item icon="calendar" :href="route('production.calendar')" :current="request()->routeIs('production.calendar')" wire:navigate>
                        {{ __('Planning Atelier') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text" :href="route('recipes.index')" :current="request()->routeIs('recipes.*')" wire:navigate>
                        {{ __('Fiches Techniques') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="archive-box" :href="route('stock.index')" :current="request()->routeIs('stock.*')" wire:navigate>
                        {{ __('Gestion du Stock') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Approvisionnement')" class="grid">
                    <flux:sidebar.item icon="building-storefront" :href="route('suppliers.index')" :current="request()->routeIs('suppliers.*')" wire:navigate>
                        {{ __('Fournisseurs') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="truck" :href="route('delivery.index')" :current="request()->routeIs('delivery.*')" wire:navigate>
                        {{ __('Livreurs & Services') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Finance')" class="grid">
                    <flux:sidebar.item icon="banknotes" :href="route('transactions.index')" :current="request()->routeIs('transactions.index')" wire:navigate>
                        {{ __('Transactions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="receipt-percent" :href="route('transactions.unpaid')" :current="request()->routeIs('transactions.unpaid')" wire:navigate>
                        {{ __('Non soldées') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('invoices.index')" :current="request()->routeIs('invoices.*')" wire:navigate>
                        {{ __('Factures & Reçus') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>


            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                @can('manage-settings')
                    <flux:sidebar.item icon="users" :href="route('settings.users')" :current="request()->routeIs('settings.users')" wire:navigate>
                        {{ __('Gestion des Employés') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('settings.index')" :current="request()->routeIs('settings.index')" wire:navigate>
                        {{ __('Configuration Système') }}
                    </flux:sidebar.item>
                @endcan
                @role('ghost')
                    <flux:sidebar.item icon="command-line" :href="route('terminal')" wire:navigate>
                        {{ __('Terminal & System Logs') }}
                    </flux:sidebar.item>
                @endrole
            </flux:sidebar.nav>

            <div class="hidden lg:block w-full pt-2 border-t border-zinc-200">
                <flux:dropdown position="right" align="end" class="w-full">
                    
                    <flux:profile
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        :avatar="auth()->user()->avatarUrl()"
                        icon-trailing="chevron-up"
                        class="w-full justify-between cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 p-2 rounded-lg transition-colors"
                    />

                    <flux:menu class="w-64">
                        <div class="px-2 py-1.5 text-sm font-normal">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate text-xs">{{ auth()->user()->email }}</flux:text>
                        </div>

                        <flux:menu.separator />

                        <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>
                            {{ __('Mon Profil Personnel') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        <div class="flex items-center justify-center gap-1 px-2 py-2" x-data>
                            <flux:tooltip toggleable>
                                <button type="button" @click="$flux.appearance = 'light'" class="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700" :class="{ 'bg-zinc-100 dark:bg-zinc-700': $flux.appearance === 'light' }" aria-label="{{ __('Light') }}">
                                    <flux:icon.sun class="size-4" />
                                </button>
                                <flux:tooltip.content>{{ __('Light') }}</flux:tooltip.content>
                            </flux:tooltip>
                            <flux:tooltip toggleable>
                                <button type="button" @click="$flux.appearance = 'dark'" class="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700" :class="{ 'bg-zinc-100 dark:bg-zinc-700': $flux.appearance === 'dark' }" aria-label="{{ __('Dark') }}">
                                    <flux:icon.moon class="size-4" />
                                </button>
                                <flux:tooltip.content>{{ __('Dark') }}</flux:tooltip.content>
                            </flux:tooltip>
                            <flux:tooltip toggleable>
                                <button type="button" @click="$flux.appearance = 'system'" class="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700" :class="{ 'bg-zinc-100 dark:bg-zinc-700': $flux.appearance === 'system' }" aria-label="{{ __('System') }}">
                                    <flux:icon.computer-desktop class="size-4" />
                                </button>
                                <flux:tooltip.content>{{ __('System') }}</flux:tooltip.content>
                            </flux:tooltip>
                        </div>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer text-red-600 dark:text-red-400">
                                {{ __('Se déconnecter') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </flux:sidebar>

        <flux:header class="z-20 lg:hidden shadow-sm">
            <flux:sidebar.toggle class="" icon="bars-3" inset="left" />
            <flux:spacer />

            <livewire:notification-bell />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    :avatar="auth()->user()->avatarUrl()"
                    icon-trailing="chevron-down"
                    class="cursor-pointer"
                />

                <flux:menu class="w-64">
                    <div class="px-2 py-1.5 text-sm font-normal">
                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                        <flux:text class="truncate text-xs">{{ auth()->user()->email }}</flux:text>
                    </div>

                        <flux:menu.separator />

                        <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>
                            {{ __('Mon Profil Personnel') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        <div class="flex items-center justify-center gap-1 px-2 py-2" x-data>
                            <flux:tooltip toggleable>
                                <button type="button" @click="$flux.appearance = 'light'" class="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700" :class="{ 'bg-zinc-100 dark:bg-zinc-700': $flux.appearance === 'light' }" aria-label="{{ __('Light') }}">
                                    <flux:icon.sun class="size-4" />
                                </button>
                                <flux:tooltip.content>{{ __('Light') }}</flux:tooltip.content>
                            </flux:tooltip>
                            <flux:tooltip toggleable>
                                <button type="button" @click="$flux.appearance = 'dark'" class="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700" :class="{ 'bg-zinc-100 dark:bg-zinc-700': $flux.appearance === 'dark' }" aria-label="{{ __('Dark') }}">
                                    <flux:icon.moon class="size-4" />
                                </button>
                                <flux:tooltip.content>{{ __('Dark') }}</flux:tooltip.content>
                            </flux:tooltip>
                            <flux:tooltip toggleable>
                                <button type="button" @click="$flux.appearance = 'system'" class="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700" :class="{ 'bg-zinc-100 dark:bg-zinc-700': $flux.appearance === 'system' }" aria-label="{{ __('System') }}">
                                    <flux:icon.computer-desktop class="size-4" />
                                </button>
                                <flux:tooltip.content>{{ __('System') }}</flux:tooltip.content>
                            </flux:tooltip>
                        </div>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer text-red-600 dark:text-red-400">
                                {{ __('Se déconnecter') }}
                            </flux:menu.item>
                        </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>