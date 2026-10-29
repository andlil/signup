import markdownEditor from './markdown-editor';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('markdownEditor', markdownEditor);
});
