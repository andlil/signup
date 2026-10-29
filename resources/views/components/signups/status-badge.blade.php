@props(['status'])

<span {{ $attributes->class([
    'inline-block rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-teal-50 text-teal-800' => $status === \App\Enums\SignupStatus::Confirmed,
    'bg-amber-50 text-amber-800' => $status === \App\Enums\SignupStatus::Waitlisted,
    'bg-stone-100 text-stone-600' => $status === \App\Enums\SignupStatus::Cancelled,
]) }}>{{ $status->label() }}</span>
