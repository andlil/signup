@props(['status'])

<span {{ $attributes->class([
    'inline-block rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-amber-50 text-amber-800' => $status === \App\Enums\EventStatus::Draft,
    'bg-teal-50 text-teal-800' => $status === \App\Enums\EventStatus::Published,
    'bg-stone-100 text-stone-600' => $status === \App\Enums\EventStatus::Closed,
]) }}>{{ $status->label() }}</span>
