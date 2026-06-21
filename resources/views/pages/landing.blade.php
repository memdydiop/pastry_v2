<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <title>{{ config('app.name', 'Pastry SaaS') }} — Gestion de pâtisserie tout-en-un</title>
        <meta name="description" content="Gérez votre pâtisserie en ligne : commandes, recettes, stocks, facturation et livraison. Une solution SaaS complète pour l'Afrique." />
    </head>
    <body class="bg-neutral-950 text-white antialiased">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-white/5 bg-neutral-950/80 backdrop-blur-sm">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold">
                    <x-app-logo-icon class="size-6 fill-current text-amber-400" />
                    <span class="text-sm font-semibold">Pastry</span>
                </a>
                <nav class="flex items-center gap-4 text-sm">
                    <a href="#features" class="text-neutral-400 hover:text-white transition-colors">Fonctionnalités</a>
                    <a href="#pricing" class="text-neutral-400 hover:text-white transition-colors">Tarifs</a>
                    <a href="{{ route('super-admin.login') }}" class="text-neutral-400 hover:text-white transition-colors">Administration</a>
                    <flux:button href="{{ route('onboarding') }}" variant="primary" size="sm" class="!bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
                        Commencer
                    </flux:button>
                </nav>
            </div>
        </header>

        <main>
            <section class="relative isolate overflow-hidden pt-32 pb-24">
                <div class="absolute inset-0 -z-10 bg-[radial-gradient(45%_40%_at_50%_60%,rgba(251,191,36,0.15),transparent)]"></div>
                <div class="mx-auto max-w-7xl px-6 text-center">
                    <div class="mx-auto inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1 text-sm text-neutral-400 mb-8">
                        SaaS pour pâtisseries africaines
                        <span class="size-1.5 rounded-full bg-amber-400"></span>
                    </div>
                    <h1 class="mx-auto max-w-4xl text-5xl font-bold leading-tight sm:text-6xl lg:text-7xl">
                        Gérez votre pâtisserie
                        <span class="text-amber-400">100% en ligne</span>
                    </h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg text-neutral-400">
                        Commandes, recettes, stocks, facturation et livraison, le tout dans une plateforme
                        simple et adaptée aux besoins des pâtisseries en Afrique.
                    </p>
                    <div class="mt-10 flex items-center justify-center gap-4">
                        <flux:button href="{{ route('onboarding') }}" variant="primary" size="lg" class="!bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
                            Démarrer maintenant
                        </flux:button>
                        <flux:button href="#features" size="lg" class="!border-white/10 !text-white hover:!bg-white/5">
                            En savoir plus
                        </flux:button>
                    </div>
                </div>
            </section>

            <section id="features" class="py-24">
                <div class="mx-auto max-w-7xl px-6">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold sm:text-4xl">Tout ce dont vous avez besoin</h2>
                        <p class="mt-4 text-neutral-400">Une plateforme complète pour gérer votre pâtisserie du four au client.</p>
                    </div>
                    <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['icon' => 'shopping-cart', 'title' => 'Commandes', 'desc' => 'Gérez les commandes en ligne, en boutique et pour les événements spéciaux.'],
                            ['icon' => 'book-open', 'title' => 'Recettes', 'desc' => 'Standardisez vos recettes avec les coûts matières et les fiches techniques.'],
                            ['icon' => 'archive-box', 'title' => 'Stocks', 'desc' => 'Suivez vos ingrédients en temps réel avec alertes de rupture.'],
                            ['icon' => 'document-text', 'title' => 'Facturation', 'desc' => 'Générez des factures et suivez les impayés facilement.'],
                            ['icon' => 'truck', 'title' => 'Livraison', 'desc' => 'Planifiez les livraisons et suivez les tournées.'],
                            ['icon' => 'users', 'title' => 'Équipe', 'desc' => 'Collaborez avec votre équipe avec des rôles et permissions.'],
                        ] as $feature)
                            <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-8 transition-colors hover:border-amber-400/20">
                                <div class="flex size-12 items-center justify-center rounded-xl bg-amber-400/10 text-amber-400">
                                    <flux:icon name="{{ $feature['icon'] }}" class="size-6" />
                                </div>
                                <h3 class="mt-6 text-lg font-semibold">{{ $feature['title'] }}</h3>
                                <p class="mt-2 text-sm text-neutral-400">{{ $feature['desc'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="pricing" class="py-24">
                <div class="mx-auto max-w-7xl px-6">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold sm:text-4xl">Des tarifs simples</h2>
                        <p class="mt-4 text-neutral-400">Pas de frais cachés. Pas de commission sur les transactions.</p>
                    </div>
                    <div class="mt-16 grid gap-8 lg:grid-cols-3">
                        @foreach (App\Models\Plan::all() as $plan)
                            <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-8 @if($loop->index === 1) relative border-amber-400/40 bg-amber-400/[0.03] @endif">
                                @if($loop->index === 1)
                                    <span class="mb-4 inline-flex items-center rounded-full bg-amber-400/10 px-3 py-1 text-xs font-medium text-amber-400">Populaire</span>
                                @endif
                                <h3 class="text-xl font-bold">{{ $plan->name }}</h3>
                                <p class="mt-1 text-sm text-neutral-400">{{ $plan->description }}</p>
                                <p class="mt-6">
                                    <span class="text-4xl font-bold">{{ number_format($plan->price, 0, ',', ' ') }}</span>
                                    <span class="text-sm text-neutral-400"> XOF / mois</span>
                                </p>
                                <ul class="mt-8 space-y-3 text-sm">
                                    <li class="flex items-center gap-3">
                                        <flux:icon name="check-circle" class="size-4 text-amber-400 shrink-0" />
                                        <span>{{ $plan->getLimit('max_users') ? $plan->getLimit('max_users') . ' utilisateur(s)' : 'Utilisateurs illimités' }}</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <flux:icon name="check-circle" class="size-4 text-amber-400 shrink-0" />
                                        <span>{{ $plan->getLimit('max_orders') ? $plan->getLimit('max_orders') . ' commandes/mois' : 'Commandes illimitées' }}</span>
                                    </li>
                                    @foreach ([
                                        'stock_management' => 'Gestion des stocks',
                                        'invoicing' => 'Facturation',
                                        'recipes' => 'Recettes',
                                        'suppliers' => 'Fournisseurs',
                                        'multi_user' => 'Multi-utilisateurs',
                                    ] as $feature => $label)
                                        <li class="flex items-center gap-3">
                                            @if($plan->hasFeature(\App\Enums\PlanFeature::from($feature)))
                                                <flux:icon name="check-circle" class="size-4 text-amber-400 shrink-0" />
                                                <span>{{ $label }}</span>
                                            @else
                                                <flux:icon name="x-circle" class="size-4 text-neutral-600 shrink-0" />
                                                <span class="text-neutral-600">{{ $label }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                <flux:button href="{{ route('onboarding', ['plan' => $plan->id]) }}" class="mt-8 w-full" variant="primary" size="lg" @class(['!bg-amber-400 !text-neutral-950 hover:!bg-amber-300' => $loop->index === 1])>
                                    {{ $plan->price > 0 ? 'S\'abonner' : 'Commencer gratuitement' }}
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="py-24">
                <div class="mx-auto max-w-7xl px-6 text-center">
                    <h2 class="text-3xl font-bold sm:text-4xl">Prêt à moderniser votre pâtisserie ?</h2>
                    <p class="mt-4 text-neutral-400">Créez votre compte en quelques minutes. Aucune carte bancaire requise.</p>
                    <div class="mt-10 flex items-center justify-center gap-4">
                        <flux:button href="{{ route('onboarding') }}" variant="primary" size="lg" class="!bg-amber-400 !text-neutral-950 hover:!bg-amber-300">
                            Commencer gratuitement
                        </flux:button>
                    </div>
                    <p class="mt-6 text-xs text-neutral-600">Paiement mobile (Wave, Orange Money, MTN MoMo) via CinetPay</p>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/5 py-8">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 text-sm text-neutral-500">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
                <div class="flex items-center gap-2">
                    <span>Paiements sécurisés par</span>
                    <span class="font-semibold text-neutral-400">CinetPay</span>
                </div>
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast variant="success" />
                <flux:toast variant="danger" />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
