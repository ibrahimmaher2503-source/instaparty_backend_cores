@props(['interactive' => false])

{{--
    White surface on the near-white page ground, separated by a border rather than a
    shadow. The Ambient Shadow Rule: a shadow appears only to signal interactivity on
    hover, never to rank two static surfaces.

    Cards are never nested. If content inside a card needs grouping, use spacing and
    a hairline rule, not a second card.
--}}
<div
    {{ $attributes->class([
        'rounded-lg border border-ink-200 bg-white',
        'transition-shadow duration-150 hover:shadow-ambient' => $interactive,
    ]) }}
>
    {{ $slot }}
</div>
