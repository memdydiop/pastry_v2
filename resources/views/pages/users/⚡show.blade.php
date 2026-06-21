<?php

use App\Models\Experience;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil')] class extends Component {
    public User $user;

    public array $experiences = [];

    public function mount(User $user): void
    {
        if ($user->hasRole('ghost')) {
            abort_if(!auth()->user()->hasRole('ghost'), 404);
        }

        $this->user = $user->load('roles');
        $this->loadExperiences();
    }

    public function loadExperiences(): void
    {
        $this->experiences = Experience::where('user_id', $this->user->id)
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->get()
            ->toArray();
    }

    public function roleBadge(): ?string
    {
        $roles = $this->user->roles;
        if ($roles->isEmpty()) return null;

        return $roles->first()->name;
    }

    public function initials(): string
    {
        return $this->user->initials();
    }

    public function profileCompletion(): int
    {
        $fields = ['name', 'email', 'phone', 'bio', 'designation', 'website', 'city', 'country', 'address', 'skills'];
        $user = $this->user;
        $filled = collect($fields)->filter(fn ($f) => !empty($user->$f))->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    public function skillsList(): array
    {
        if (empty($this->user->skills)) return [];

        return array_map('trim', explode(',', $this->user->skills));
    }

    public function coverImageUrl(): ?string
    {
        return $this->user->coverUrl();
    }

    public function coverStyle(): string
    {
        return 'linear-gradient(to right, #405189, #3577f1, #0ab39c)';
    }
}; ?>

<div class="space-y-6">

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- VELZON PROFILE BANNER & COVER --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="-mx-6! -mt-6! relative">
        <!-- Profile Cover Background -->
        <div class="h-80! sm:h-64 overflow-hidden absolute inset-x-0 top-0">
            @if ($this->coverImageUrl())
                <img src="{{ $this->coverImageUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @endif
            <div class="absolute inset-0" style="background: {{ $this->coverStyle() }}; {{ $this->coverImageUrl() ? 'opacity: 0;' : '' }}"></div>
            <div class="absolute inset-0 bg-blue-950/90"></div>
        </div>
    </div>

    

    <div class="relative z-10">
        <!-- Profile Header Content Box -->
        <div class="pt-6 relative z-10">
            
            <div class="mb-4 py-6">
                <div class="flex flex-col md:flex-row items-center md:items-end justify-between gap-6">
                    
                    <!-- Avatar & Essential Info -->
                    <div class="flex flex-col sm:flex-row items-center gap-5 text-center sm:text-left w-full md:w-auto">
                        <div class="flex justify-center items-center size-24 sm:size-28 bg-white mask-squircle">
                            <flux:avatar
                            mask="squircle"
                            :name="$this->user->name"
                            :initials="$this->initials()"
                            :src="$this->user->avatarUrl()"
                            class="size-20! sm:size-24! shadow-md shrink-0"
                        />
                        </div>
                        
                        <div class="space-y-1 pb-1">
                            <h3 class="text-white font-bold text-xl sm:text-2xl flex items-center justify-center sm:justify-start gap-2">
                                {{ $this->user->name }}
                                <svg class="size-5 text-blue-500 fill-blue-500 shrink-0" viewBox="0 0 24 24">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            </h3>
                            <p class="text-zinc-500 dark:text-zinc-400 font-medium text-sm">{{ $this->user->designation ?? 'Développeur / Membre' }}</p>
                            
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-x-4 gap-y-1 text-zinc-400 dark:text-zinc-500 text-xs font-medium pt-1">
                                @if ($this->user->city || $this->user->country)
                                    <span class="flex items-center gap-1">
                                        <svg class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        {{ collect([$this->user->city, $this->user->country])->filter()->join(', ') }}
                                    </span>
                                @endif
                                @if ($this->roleBadge())
                                    <span class="flex items-center gap-1">
                                        <svg class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                        </svg>
                                        {{ $this->roleBadge() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Counter Row -->
                    <div class="flex items-center justify-center gap-6 sm:gap-10 border-t sm:border-t-0 border-zinc-100 dark:border-zinc-800 w-full md:w-auto pt-4 md:pt-0 shrink-0">
                        <div class="text-center">
                            <h4 class="text-zinc-100 font-bold text-lg sm:text-xl">{{ count($this->experiences) }}</h4>
                            <p class="text-zinc-400 dark:text-zinc-500 text-xs font-semibold uppercase tracking-wider">{{ __('Projets') }}</p>
                        </div>
                        <div class="text-center">
                            <h4 class="text-zinc-100 font-bold text-lg sm:text-xl">{{ count($this->skillsList()) }}</h4>
                            <p class="text-zinc-400 dark:text-zinc-500 text-xs font-semibold uppercase tracking-wider">{{ __('Compétences') }}</p>
                        </div>
                        <div class="text-center">
                            <h4 class="text-zinc-100 font-bold text-lg sm:text-xl">{{ $this->profileCompletion() }}%</h4>
                            <p class="text-zinc-400 dark:text-zinc-500 text-xs font-semibold uppercase tracking-wider">{{ __('Complétion') }}</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- NAVIGATION TABS (Velzon Layout Navigation Structure)           --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div x-data="{ activeTab: 'overview' }">
            <!-- Velzon Navigation Bar Container -->
            <div class="relative -mx-6 px-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2 ">
                <nav class="flex flex-wrap gap-1" aria-label="Profile tabs">
                    <button
                        @click="activeTab = 'overview'"
                        :class="activeTab === 'overview'
                            ? 'bg-white/20 backdrop-blur-2xl text-white shadow-xs font-semibold'
                            : 'text-white/60 hover:text-white/80'"
                        class="px-5 py-2 text-xs font-medium rounded transition-all duration-150 flex items-center gap-2"
                        type="button"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        {{ __('Vue d\'ensemble') }}
                    </button>
                    
                    <button
                        @click="activeTab = 'activities'"
                        :class="activeTab === 'activities'
                            ? 'bg-white/20 backdrop-blur-2xl text-white shadow-xs font-semibold'
                            : 'text-white/60 hover:text-white/80'"
                        class="px-5 py-2 text-xs font-medium rounded transition-all duration-150 flex items-center gap-2"
                        type="button"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        {{ __('Activités & Expériences') }}
                    </button>

                    <button
                        @click="activeTab = 'documents'"
                        :class="activeTab === 'documents'
                            ? 'bg-white/20 backdrop-blur-2xl text-white shadow-xs font-semibold'
                            : 'text-white/60 hover:text-white/80'"
                        class="px-5 py-2 text-xs font-medium rounded transition-all duration-150 flex items-center gap-2"
                        type="button"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        {{ __('Documents') }}
                    </button>
                </nav>

            </div>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- TAB: OVERVIEW (Layout 1/4 Left Side, 3/4 Right Side)           --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div
                x-show="activeTab === 'overview'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-6"
            >
                <div class="grid grid-cols-1 sm:grid-cols-12 xl:grid-cols-11 2xl:grid-cols-12 gap-4">

                    {{-- ─── LEFT COLUMN SIDEBAR (1/4) ─── --}}
                    <div class="sm:col-span-4 xl:col-span-3 2xl:col-span-3 space-y-4">

                        <!-- Profil Completion Progress -->
                        <div class="rounded border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-2xs p-5">
                            <h5 class="text-sm font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-4">{{ __('Complétion du Profil') }}</h5>
                            <div class="space-y-2">
                                <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden h-2">
                                    <div class="h-full rounded-full transition-all duration-500 ease-out" style="width: {{ $this->profileCompletion() }}%; background: linear-gradient(90deg, #405189, #0ab39c);"></div>
                                </div>
                                <div class="flex justify-end text-xs font-bold text-[#0ab39c]">{{ $this->profileCompletion() }}%</div>
                            </div>
                        </div>

                        <!-- Personal Information Grid Table -->
                        <div class="rounded border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-2xs p-5">
                            <h5 class="text-sm font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-4">{{ __('Informations Personnelles') }}</h5>
                            <div class="overflow-x-auto">
                                
                                <table class="w-full text-sm text-zinc-600 dark:text-zinc-300">
                                    <tbody>
                                        <tr class="border-b border-zinc-100">
                                            <td class="py-3 text-zinc-600 truncate">
                                                <div class="text-xs font-medium">
                                                    {{ __('Nom Complet') }} :
                                                </div>
                                                <div class=" text-muted dark:text-zinc-200">
                                                    {{ $this->user->name }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="border-b border-zinc-100">
                                            <td class="py-3 text-zinc-600 truncate">
                                                <div class="text-xs font-medium">
                                                    {{ __('Téléphone') }} :
                                                </div>
                                                <div class="text-muted dark:text-zinc-200">
                                                    {{ $this->user->phone ?? '—' }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="border-b border-zinc-100">
                                            <td class="py-3 text-zinc-600 truncate">
                                                <div class="text-xs font-medium">
                                                    {{ __('Adresse E-mail') }} :
                                                </div>
                                                <div class="text-muted dark:text-zinc-200">
                                                    {{ $this->user->email }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="border-b border-zinc-100">
                                            <td class="py-3 text-zinc-600 truncate">
                                                <div class="text-xs font-medium">
                                                    {{ __('Localisation') }} :
                                                </div>
                                                <div class="text-muted dark:text-zinc-200">
                                                    {{ collect([$this->user->city, $this->user->country])->filter()->join(', ') ?: '—' }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="border-b border-zinc-100">
                                            <td class="py-3 text-zinc-600 truncate">
                                                <div class="text-xs font-medium">
                                                    {{ __('Rejoint le') }}
                                                </div>
                                                <div class="text-muted dark:text-zinc-200">
                                                    {{ $this->user->joining_date?->format('d M Y') ?? '—' }}
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Skills Badges Block -->
                        @if (!empty($this->skillsList()))
                        <div class="rounded border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-2xs p-5">
                            <h5 class="text-sm font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-4">{{ __('Compétences Techniques') }}</h5>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($this->skillsList() as $skill)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200/50 dark:border-zinc-700/30">
                                        {{ $skill }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                    </div>

                    {{-- ─── RIGHT MAIN COLUMN (3/4) ─── --}}
                    <div class="sm:col-span-8 xl:col-span-8 2xl:col-span-9 space-y-4 ">

                        <!-- Biography / About Me Block -->
                        <div class="rounded border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-2xs p-6">
                            <h5 class="text-sm font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-4">{{ __('Biographie & Présentation') }}</h5>
                            <div class="prose dark:prose-invert max-w-none text-zinc-600 dark:text-zinc-300 text-sm leading-relaxed">
                                @if ($this->user->bio)
                                    <p>{{ $this->user->bio }}</p>
                                @else
                                    <p class="text-zinc-400 italic">{{ __('Aucune description ou biographie enregistrée pour le moment.') }}</p>
                                @endif
                            </div>

                            <!-- Sub-details Row (Designation & Website URL) -->
                            @if ($this->user->designation || $this->user->website)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6 pt-6 border-t border-zinc-100 dark:border-zinc-800/80">
                                @if ($this->user->designation)
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0 size-9 rounded-lg bg-[#405189]/10 text-[#405189] dark:bg-blue-500/10 dark:text-blue-400 flex items-center justify-center">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 0 1 3.75 18.4V14.15m16.5 0c0-1.22-.821-2.275-2.02-2.477a16.326 16.326 0 0 0-12.46 0 2.477 2.477 0 0 0-2.02 2.477m16.5 0v-1.424c0-1.218-.816-2.273-2.015-2.478a16.163 16.163 0 0 0-12.47 0A2.478 2.478 0 0 0 3.75 12.726v1.424M18 10.5V7.5A2.25 2.25 0 0 0 15.75 5.25h-7.5A2.25 2.25 0 0 0 6 7.5v3" />
                                        </svg>
                                    </div>
                                    <div class="overflow-hidden">
                                        <p class="text-zinc-400 dark:text-zinc-500 text-xs font-semibold uppercase tracking-wider mb-0.5">{{ __('Poste Actuel') }}</p>
                                        <h6 class="truncate text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $this->user->designation }}</h6>
                                    </div>
                                </div>
                                @endif

                                @if ($this->user->website)
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0 size-9 rounded-lg bg-[#0ab39c]/10 text-[#0ab39c] flex items-center justify-center">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                        </svg>
                                    </div>
                                    <div class="overflow-hidden">
                                        <p class="text-zinc-400 dark:text-zinc-500 text-xs font-semibold uppercase tracking-wider mb-0.5">{{ __('Site Web Global') }}</p>
                                        <a href="{{ $this->user->website }}" target="_blank" class="font-bold text-sm text-blue-600 dark:text-blue-400 hover:underline truncate block">{{ $this->user->website }}</a>
                                    </div>
                                </div>
                                @endif
                            </div>
                            @endif
                        </div>

                        <!-- Velzon Filterable Recent Activity Timeline Block -->
                        @if (!empty($this->experiences))
                        <div class="rounded border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-2xs" x-data="{ activityFilter: 'all' }">
                            <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-200 dark:border-zinc-700/80">
                                <h5 class="text-sm font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-0">{{ __('Activités Récentes') }}</h5>
                                <div class="flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg p-1">
                                    <button @click="activityFilter = 'all'" :class="activityFilter === 'all' ? 'bg-white dark:bg-zinc-700 text-zinc-800 dark:text-white shadow-xs' : 'text-zinc-500'" class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all duration-150" type="button">Tout</button>
                                    <button @click="activityFilter = 'recent'" :class="activityFilter === 'recent' ? 'bg-white dark:bg-zinc-700 text-zinc-800 dark:text-white shadow-xs' : 'text-zinc-500'" class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all duration-150" type="button">Récent</button>
                                </div>
                            </div>
                            
                            <div class="p-6">
                                <!-- Vertical Line Timeline Structure -->
                                <div class="relative border-l-2 border-zinc-100 dark:border-zinc-800 space-y-6 ml-2">
                                    @foreach ($this->experiences as $exp)
                                        <div class="relative pl-6 group">
                                            <!-- Timeline Node Icon Circle Indicator -->
                                            <div class="absolute -left-[7px] top-1.5 size-3 rounded-full bg-white dark:bg-zinc-900 border-2 border-[#405189] dark:border-blue-500 ring-4 ring-transparent group-hover:ring-[#405189]/10 transition-all duration-150"></div>
                                            
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                <div>
                                                    <h6 class="font-bold text-sm text-zinc-800 dark:text-zinc-100">{{ $exp['title'] }}</h6>
                                                    @if ($exp['company'])
                                                        <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 px-2 py-0.5 rounded border border-zinc-200/40 dark:border-zinc-700/20 inline-block mt-0.5">{{ $exp['company'] }}</span>
                                                    @endif
                                                </div>
                                                <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500 shrink-0">
                                                    {{ \Carbon\Carbon::parse($exp['start_date'])->format('M Y') }} — 
                                                    @if ($exp['end_date']) {{ \Carbon\Carbon::parse($exp['end_date'])->format('M Y') }}
                                                    @else <span class="text-[#0ab39c] font-semibold">{{ __('Présent') }}</span>
                                                    @endif
                                                </span>
                                            </div>
                                            @if ($exp['description'])
                                                <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-2 leading-relaxed max-w-4xl">{{ $exp['description'] }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif

                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- TAB: ACTIVITIES & EXPERIENCES (Full Layout View)               --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div
                x-show="activeTab === 'activities'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-6"
            >
                <div class="rounded border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-2xs p-6">
                    <div class="border-b border-zinc-100 dark:border-zinc-800/80 pb-4 mb-6">
                        <h5 class="text-base font-bold text-zinc-800 dark:text-zinc-200">{{ __('Parcours Professionnel & Projets') }}</h5>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">{{ __('Historique complet des rôles et expériences de travail enregistrés.') }}</p>
                    </div>

                    <div class="relative border-l-2 border-zinc-100 dark:border-zinc-800 space-y-8 ml-2">
                        @forelse ($this->experiences as $exp)
                            <div class="relative pl-6 group">
                                <div class="absolute -left-[7px] top-1.5 size-3 rounded-full bg-white dark:bg-zinc-900 border-2 border-[#3577f1] ring-4 ring-transparent group-hover:ring-[#3577f1]/10 transition-all duration-150"></div>
                                
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <h6 class="font-bold text-base text-zinc-800 dark:text-zinc-100">{{ $exp['title'] }}</h6>
                                        @if ($exp['company'])
                                            <p class="text-sm font-semibold text-[#3577f1] dark:text-blue-400 mt-0.5">{{ $exp['company'] }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs font-semibold text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-800 px-2.5 py-1 rounded-md border border-zinc-100 dark:border-zinc-700/40 shrink-0 self-start sm:self-center">
                                        {{ \Carbon\Carbon::parse($exp['start_date'])->format('M Y') }} — 
                                        @if ($exp['end_date']) {{ \Carbon\Carbon::parse($exp['end_date'])->format('M Y') }}
                                        @else {{ __('Présent') }}
                                        @endif
                                    </span>
                                </div>
                                @if ($exp['description'])
                                    <p class="text-zinc-600 dark:text-zinc-400 text-sm mt-3 leading-relaxed border-t border-zinc-50 dark:border-zinc-800/30 pt-3 max-w-none">{{ $exp['description'] }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-8 text-zinc-400 italic text-sm pl-6">
                                {{ __('Aucune expérience professionnelle listée pour le moment.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- TAB: DOCUMENTS (Velzon Dynamic Empty/List Layout)              --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div
                x-show="activeTab === 'documents'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-6"
            >
                <div class="rounded border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-2xs p-6">
                    <div class="border-b border-zinc-100 dark:border-zinc-800/80 pb-4 mb-6">
                        <h5 class="text-base font-bold text-zinc-800 dark:text-zinc-200">{{ __('Portefeuille de Documents') }}</h5>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">{{ __('Pièces jointes, CV, certifications ou documents partagés.') }}</p>
                    </div>
                    
                    <!-- Velzon Professional Empty State Illustration layout -->
                    <div class="text-center py-12">
                        <div class="size-16 rounded-full bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-4 border border-zinc-100 dark:border-zinc-700/50">
                            <svg class="size-8 text-zinc-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m12-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <h6 class="font-bold text-sm text-zinc-700 dark:text-zinc-300">{{ __('Aucun document disponible') }}</h6>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1 max-w-sm mx-auto">{{ __('Les fichiers chargés comme des CV ou portefeuilles de projets apparaîtront dans cette section.') }}</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
