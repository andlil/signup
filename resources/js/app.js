import markdownEditor from './markdown-editor';
import initVacationCalendars from './vacation-calendar';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('markdownEditor', markdownEditor);
});

document.addEventListener('DOMContentLoaded', () => initVacationCalendars());
