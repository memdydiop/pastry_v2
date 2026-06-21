@props([
    'title' => null,
])

<div class="flex justify-between">
    <div>
        <flux:heading size="lg" level="3" class="dark:text-white dark:hover:text-white text-[1.125rem]">{{ $title }}</flux:heading>
    </div>
</div>