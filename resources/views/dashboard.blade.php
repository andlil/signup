<x-layouts.app :title="__('My events')">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('My events') }}</h1>

    <div class="mt-6 rounded-lg border border-dashed border-stone-300 bg-white px-6 py-12 text-center">
        <p class="font-medium">{{ __('You have no events yet.') }}</p>
        <p class="mt-1 text-sm text-stone-600">{{ __('Creating events is coming in the next version.') }}</p>
    </div>
</x-layouts.app>
