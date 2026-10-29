@props(['id', 'property'])

@php
    $buttons = [
        ['label' => __('Bold'), 'icon' => 'B', 'class' => 'font-bold', 'active' => "isActive('bold')", 'click' => "run('toggleBold')"],
        ['label' => __('Italic'), 'icon' => 'I', 'class' => 'italic', 'active' => "isActive('italic')", 'click' => "run('toggleItalic')"],
        ['label' => __('Heading'), 'icon' => 'H', 'class' => 'font-semibold', 'active' => "isActive('heading', { level: 2 })", 'click' => "run('toggleHeading', { level: 2 })"],
        ['label' => __('Subheading'), 'icon' => 'h', 'class' => 'font-semibold', 'active' => "isActive('heading', { level: 3 })", 'click' => "run('toggleHeading', { level: 3 })"],
        ['label' => __('Bulleted list'), 'icon' => '•', 'class' => '', 'active' => "isActive('bulletList')", 'click' => "run('toggleBulletList')"],
        ['label' => __('Numbered list'), 'icon' => '1.', 'class' => '', 'active' => "isActive('orderedList')", 'click' => "run('toggleOrderedList')"],
        ['label' => __('Quote'), 'icon' => '❝', 'class' => '', 'active' => "isActive('blockquote')", 'click' => "run('toggleBlockquote')"],
        ['label' => __('Link'), 'icon' => '🔗', 'class' => '', 'active' => "isActive('link')", 'click' => 'setLink()'],
    ];
@endphp

<div wire:ignore
    x-data="markdownEditor({ property: @js($property), linkPrompt: @js(__('Link address (leave empty to remove the link)')) })"
    {{ $attributes->class('mt-1 overflow-hidden rounded-md border border-stone-300 bg-white shadow-sm focus-within:border-teal-600 focus-within:ring-2 focus-within:ring-teal-600/20') }}>
    <div role="toolbar" aria-label="{{ __('Formatting') }}" aria-controls="{{ $id }}" class="flex flex-wrap gap-1 border-b border-stone-200 bg-stone-50 px-2 py-1.5">
        @foreach ($buttons as $button)
            <button type="button" title="{{ $button['label'] }}" aria-label="{{ $button['label'] }}"
                x-on:mousedown.prevent
                x-on:click="{{ $button['click'] }}"
                x-bind:aria-pressed="{{ $button['active'] }} ? 'true' : 'false'"
                x-bind:class="{{ $button['active'] }} ? 'bg-stone-800 text-white' : 'text-stone-700 hover:bg-stone-200'"
                class="min-w-8 rounded px-2 py-1 text-sm {{ $button['class'] }}">{{ $button['icon'] }}</button>
        @endforeach
    </div>
    <div id="{{ $id }}" x-ref="editor"></div>
</div>
