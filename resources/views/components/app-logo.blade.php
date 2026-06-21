@props([
    'sidebar' => false,
])

@php
    $companyName = App\Models\Setting::getValue('company_name', '');
    $companyLogo = App\Models\Setting::getValue('company_logo', '');
    $logoUrl = $companyLogo ? Storage::url($companyLogo) : null;
@endphp

@if($sidebar)
    <flux:sidebar.brand name="{{ $companyName }}" {{ $attributes }}>
        <x-slot name="logo" class="relative flex size-10 items-center justify-center overflow-hidden shrink-0">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="absolute inset-0 w-full h-full object-cover" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-zinc-800" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $companyName }}" {{ $attributes }}>
        <x-slot name="logo" class="relative flex size-10 items-center  justify-center overflow-hidden shrink-0">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="absolute inset-0 w-full h-full object-cover" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-zinc-800" />
            @endif
        </x-slot>
    </flux:brand>
@endif
