@props(['for'])

@error($for)
    <p {{ $attributes->class('mt-1 text-sm text-red-700') }}>{{ $message }}</p>
@enderror
