@props([
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => null,
])


    <div class="block justify-between py-5 mb-0! md:flex">
        
        <div>
            <flux:heading size="lg" level="3" class="dark:text-white dark:hover:text-white text-[1.125rem]">{{ $title }}</flux:heading>
            <flux:text size="sm" variant="muted">{{ $subtitle }}</flux:text>
        </div>

        <flux:breadcrumbs>
            {{ $breadcrumbs }}
        </flux:breadcrumbs>
        
    </div>