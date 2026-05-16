@props(['tone' => 'stone'])
@php
    $classes = [
        'stone' => 'bg-stone-100 text-stone-700',
        'green' => 'bg-emerald-100 text-emerald-700',
        'yellow' => 'bg-amber-100 text-amber-800',
        'red' => 'bg-rose-100 text-rose-700',
        'blue' => 'bg-sky-100 text-sky-700',
    ][$tone] ?? 'bg-stone-100 text-stone-700';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-md px-2 py-1 text-xs font-medium $classes"]) }}>{{ $slot }}</span>
