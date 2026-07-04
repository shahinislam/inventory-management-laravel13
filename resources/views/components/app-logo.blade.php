@props(['sidebar' => false])

@php
    $companyName = \App\Models\Setting::get('company.name', 'Inventory');
    $companyLogo = \App\Models\Setting::get('company.logo');
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$companyName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            @if($companyLogo)
                <img src="{{ $companyLogo }}" class="size-8 rounded-md object-cover" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$companyName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            @if($companyLogo)
                <img src="{{ $companyLogo }}" class="size-8 rounded-md object-cover" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
