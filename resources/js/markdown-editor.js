/**
 * A rich text editor that reads and writes Markdown, so organizers can format
 * descriptions without knowing the syntax.
 *
 * The editor instance lives outside Alpine's reactive data, since ProseMirror
 * does not work through Alpine's proxies. The editor code is only downloaded
 * on pages that use it.
 */
export default function markdownEditor({ property, linkPrompt }) {
    let editor = null;

    return {
        // Bumped on every editor change so the toolbar re-reads the active marks.
        revision: 0,

        async init() {
            const [{ Editor }, { default: StarterKit }, { Markdown }] = await Promise.all([
                import('@tiptap/core'),
                import('@tiptap/starter-kit'),
                import('@tiptap/markdown'),
            ]);

            editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3] },
                        underline: false,
                        codeBlock: false,
                        link: { openOnClick: false, autolink: true, protocols: ['mailto'] },
                    }),
                    Markdown,
                ],
                content: this.$wire.get(property) ?? '',
                contentType: 'markdown',
                editorProps: {
                    attributes: {
                        class: 'prose-content min-h-40 px-3 py-2 focus:outline-none',
                        'aria-multiline': 'true',
                        role: 'textbox',
                    },
                },
                onTransaction: () => {
                    this.revision++;
                },
                onUpdate: () => {
                    this.$wire.$set(property, editor.isEmpty ? '' : editor.getMarkdown().trim(), false);
                },
            });
        },

        destroy() {
            editor?.destroy();
            editor = null;
        },

        isActive(name, attributes = {}) {
            this.revision;

            return editor?.isActive(name, attributes) ?? false;
        },

        run(command, ...args) {
            editor.chain().focus()[command](...args).run();
        },

        setLink() {
            const previous = editor.getAttributes('link').href ?? '';
            const url = window.prompt(linkPrompt, previous);

            if (url === null) {
                return;
            }

            if (url.trim() === '') {
                editor.chain().focus().extendMarkRange('link').unsetLink().run();

                return;
            }

            const href = /^(https?:\/\/|mailto:)/i.test(url.trim()) ? url.trim() : `https://${url.trim()}`;

            editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
        },
    };
}
