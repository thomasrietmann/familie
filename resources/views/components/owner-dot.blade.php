@props(['event'])

@if($event->isForFamily())
    <span class="owner-dot owner-dot-rainbow" title="Ganze Familie"></span>
@else
    <span class="owner-dot" style="background-color: {{ $event->ownerColorHex() }}" title="{{ $event->ownerDisplayName() }}"></span>
@endif
