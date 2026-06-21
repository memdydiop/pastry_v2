<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\File;

new #[Title('Terminal & System Logs')] class extends Component
{
    public string $logContent = '';
    public string $search = '';
    public bool $autoRefresh = true;

    public function mount(): void
    {
        $this->loadLogs();
    }

    public function loadLogs(): void
    {
        $path = storage_path('logs/laravel.log');

        if (!File::exists($path)) {
            $this->logContent = 'Aucun fichier log trouvé.';
            return;
        }

        $content = File::get($path);
        $lines = explode("\n", $content);

        if (!empty($this->search)) {
            $lines = array_filter($lines, fn($line) => str_contains($line, $this->search));
        }

        $lines = array_slice(array_reverse($lines), 0, 200);
        $this->logContent = implode("\n", $lines);
    }

    public function clearLogs(): void
    {
        $path = storage_path('logs/laravel.log');

        if (File::exists($path)) {
            File::put($path, '');
        }

        $this->logContent = '';
        $this->dispatch('toast', variant: 'success', heading: 'Logs vidés.');
    }

    public function refresh(): void
    {
        $this->loadLogs();
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Terminal & System Logs'"
        :subtitle="'Laravel logs, debug et maintenance système.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Administration</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Terminal</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs p-4">
        <div class="flex items-center gap-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Filtrer les logs..."
                class="flex-1"
            />
            <flux:button icon="arrow-path" wire:click="refresh" class="cursor-pointer" title="Rafraîchir">
                Actualiser
            </flux:button>
            <flux:button icon="trash" variant="danger" wire:click="clearLogs" class="cursor-pointer" title="Vider les logs">
                Vider
            </flux:button>
        </div>
    </flux:card>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs p-0">
        <div class="bg-zinc-950 text-green-400 font-mono text-xs p-4 rounded-lg overflow-x-auto max-h-[70vh] overflow-y-auto leading-relaxed whitespace-pre-wrap">
            @if(empty($logContent))
                <span class="text-zinc-500">Aucune entrée de log.</span>
            @else
                {{ $logContent }}
            @endif
        </div>
    </flux:card>
</div>
