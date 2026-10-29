<x-mail::table>
| | |
|:--|:--|
| **{{ __('Event') }}** | {{ $event->title }} |
| **{{ __('When') }}** | {{ $event->starts_at->isoFormat('LLLL') }} |
@if ($event->location)
| **{{ __('Where') }}** | {{ $event->location }} |
@endif
| **{{ __('Name') }}** | {{ $signup->name }} |
| **{{ __('Email') }}** | {{ $signup->email }} |
@foreach ($event->questions as $question)
@if ($signup->displayAnswerTo($question) !== '')
| **{{ $question->label }}** | {{ str_replace(['|', "\n"], ['\|', ' '], $signup->displayAnswerTo($question)) }} |
@endif
@endforeach
</x-mail::table>
