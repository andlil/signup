@props(['variant' => 'primary'])

<button {{ $attributes->merge(['type' => 'submit'])->class([
    'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium focus:ring-2 focus:outline-none disabled:opacity-50',
    'bg-teal-700 text-white hover:bg-teal-800 focus:ring-teal-600/40' => $variant === 'primary',
    'border border-stone-300 bg-white text-stone-800 hover:bg-stone-50 focus:ring-stone-400/40' => $variant === 'secondary',
    'border border-red-200 bg-white text-red-700 hover:bg-red-50 focus:ring-red-600/30' => $variant === 'danger',
]) }}>
    {{ $slot }}
</button>
