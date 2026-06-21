@props([
    'subtitle' => null,
])

<div class="flex justify-between">
    <div>
        <flux:text size="sm" variant="muted">{{ $subtitle }}</flux:text>
    </div>
</div>