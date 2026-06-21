@props([
    'searchBinding' => 'search',
    'perPageBinding' => 'perPage',
    'searchPlaceholder' => 'Rechercher...',
    'perPageOptions' => [5, 10, 15, 20, 25, 50, 100],
    'hasActiveFilters' => false 
])

<div {{ $attributes->merge(['class' => 'pb-4 flex flex-col sm:flex-row items-start sm:items-center sm:justify-between border-b border-dashed border-zinc-100 dark:border-zinc-600']) }}>
    
    {{-- Zone Gauche : Recherche & Filtres spécifiques --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full">
        <div>
            <flux:input class="sm:w-32! md:w-48! w-full"
                wire:model.live.debounce.300ms="{{ $searchBinding }}" 
                icon="magnifying-glass" 
                size="sm"
                placeholder="{{ $searchPlaceholder }}" 
                clearable
            />
        </div>

        {{-- Slot pour les filtres personnalisés (ex: le select des rôles) --}}
        @if (isset($slot))
            <div class="flex flex-col sm:flex-row items-center gap-3 ">
                {{ $slot }}
            </div>
        @endif

        {{-- NOUVEAU : Bouton de réinitialisation dynamique --}}
        @if($hasActiveFilters)
            <flux:button 
                wire:click="clearFilters" 
                size="sm" 
                icon="x-mark"
            />
        @endif
    </div>

    {{-- Zone Droite : Sélecteur du nombre de lignes --}}
    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        @if (isset($actions))
            {{ $actions }}
        @endif

        <div class="flex items-center gap-2 shrink-0">
            <flux:text size="xs" class="text-zinc-500 whitespace-nowrap">Afficher :</flux:text>
            
            <flux:select wire:model.live="{{ $perPageBinding }}" size="sm" class="w-20">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>
</div>