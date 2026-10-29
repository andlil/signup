@props(['for'])

<label for="{{ $for }}" {{ $attributes->class('block text-sm font-medium') }}>{{ $slot }}</label>
