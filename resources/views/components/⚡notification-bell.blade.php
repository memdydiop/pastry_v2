<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Ingredient;

new class extends Component
{
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    public function refreshCount(): void
    {
        $unreadNotifications = Auth::user()->unreadNotifications()->count();
        $criticalIngredients = $this->getCriticalIngredientsProperty()->count();
        $this->unreadCount = $unreadNotifications + $criticalIngredients;
    }

    public function markAsRead(string $notificationId): void
    {
        Auth::user()->notifications()->where('id', $notificationId)->first()?->markAsRead();
        $this->refreshCount();
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        $this->refreshCount();
    }

    public function getNotificationsProperty()
    {
        return Auth::user()->notifications()->latest()->take(10)->get();
    }

    public function getCriticalIngredientsProperty()
    {
        return Ingredient::where('is_critical', true)
            ->whereColumn('stock_quantity', '<=', 'alert_threshold')
            ->orderBy('stock_quantity')
            ->get();
    }
}; ?>

<div
    x-data="{ open: false }"
    @click.away="open = false"
    class="relative"
    wire:poll.30s="refreshCount"
>
    <flux:button
        variant="ghost"
        icon="bell"
        class="relative cursor-pointer"
        @click="open = !open"
        x-bind:class="open ? 'bg-zinc-100 dark:bg-zinc-800' : ''"
    >
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center size-5 rounded-full bg-rose-500 text-white text-xs font-bold">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </flux:button>

    <div
        x-show="open"
        x-transition
        class="absolute right-0 mt-2 w-80 bg-white dark:bg-zinc-800 rounded-xl shadow-lg border border-zinc-200 dark:border-zinc-700 z-50 max-h-96 overflow-y-auto"
        style="display: none;"
    >
        <div class="sticky top-0 bg-white dark:bg-zinc-800 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
            <flux:heading size="sm" class="font-semibold">Notifications</flux:heading>
            @if($unreadCount > 0)
                <flux:button size="xs" variant="ghost" wire:click="markAllAsRead" class="cursor-pointer text-xs">
                    Tout marquer lu
                </flux:button>
            @endif
        </div>

        @php $criticalCount = $this->criticalIngredients->count(); @endphp

        @if($criticalCount > 0)
            <div class="px-4 py-2 bg-rose-50/50 dark:bg-rose-950/20 border-b border-zinc-200 dark:border-zinc-700">
                <flux:text size="xs" class="font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">Alertes en cours</flux:text>
            </div>
            @foreach($this->criticalIngredients as $ing)
                <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 last:border-b-0 bg-rose-50/30 dark:bg-rose-950/10">
                    <div class="flex items-start gap-2">
                        <flux:icon.exclamation-triangle variant="solid" class="size-5 text-rose-500 mt-0.5 shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $ing->name }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                {{ number_format($ing->stock_quantity, 2, ',', ' ') }} / {{ number_format($ing->alert_threshold, 2, ',', ' ') }} {{ $ing->unit->value }}
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        @php $notificationsList = $this->notifications; @endphp

        @if($notificationsList->isNotEmpty())
            @if($criticalCount > 0)
                <div class="px-4 py-2 bg-zinc-50/50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:text size="xs" class="font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Historique</flux:text>
                </div>
            @endif
            @foreach($notificationsList as $notification)
                <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 last:border-b-0 {{ $notification->read_at ? '' : 'bg-amber-50/30 dark:bg-amber-950/10' }}">
                    <div class="flex items-start gap-2">
                        <flux:icon.clock variant="solid" class="size-4 text-zinc-400 mt-0.5 shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $notification->data['ingredient_name'] ?? 'Ingrédient' }}
                            </p>
                            <p class="text-xs text-zinc-400 mt-0.5">
                                {{ $notification->created_at->diffForHumans() }}
                                @if(!empty($notification->data['triggered_by']))
                                    · {{ $notification->data['triggered_by'] }}
                                @endif
                            </p>
                        </div>
                        @if(!$notification->read_at)
                            <flux:button size="xs" variant="ghost" wire:click="markAsRead('{{ $notification->id }}')" class="cursor-pointer shrink-0" icon="check" title="Marquer lu" />
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        @if($criticalCount === 0 && $notificationsList->isEmpty())
            <div class="px-4 py-8 text-center">
                <flux:icon.bell-slash variant="outline" class="size-8 text-zinc-300 dark:text-zinc-600 mx-auto mb-2" />
                <flux:text size="sm" class="text-zinc-400">Aucune alerte</flux:text>
            </div>
        @endif

        <div class="sticky bottom-0 bg-white dark:bg-zinc-800 px-4 py-2 border-t border-zinc-200 dark:border-zinc-700 text-center">
            <flux:link href="{{ route('stock.index') }}" size="sm" wire:navigate>
                Voir le stock
            </flux:link>
        </div>
    </div>
</div>
