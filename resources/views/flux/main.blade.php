@blaze(fold: true)

@props([
    'container' => null,
])

@php
// Overrides the Flux default of `p-6 lg:p-8`. Every page view opens with its
// own padding wrapper, so keeping Flux's here stacked the two and produced
// 48px of gutter on desktop. Page wrappers own the spacing now.
$classes = Flux::classes('[grid-area:main]')
    ->add('[[data-flux-container]_&]:px-0') // If there is a wrapping container, let IT handle the x padding...
    ->add($container ? 'mx-auto w-full [:where(&)]:max-w-7xl' : '')
    ;
@endphp

<div {{ $attributes->class($classes) }} data-flux-main>
    {{ $slot }}
</div>
