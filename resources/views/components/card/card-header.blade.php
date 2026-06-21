@props([
    'title' => null,
    'subtitle' => null,
    'menu' => null,
])

<div class="h-14 flex justify-between items-center border-b px-4 py-2">
    <div class="card-header-title">
        <flux:heading level="3" class="dark:text-white dark:hover:text-white">{{ $title }}</flux:heading>
        <flux:text size="sm" variant="muted">{{ $subtitle }}</flux:text>
    </div>

    @if($menu)
    <flux:dropdown>
        <flux:button icon="ellipsis-horizontal" size="sm" />

        <flux:menu>
            {{ $menu }}
        </flux:menu>
    </flux:dropdown>
    @endif
        
</div>