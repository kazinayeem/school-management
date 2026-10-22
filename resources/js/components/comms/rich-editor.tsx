import { useI18n } from '@/lib/i18n';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { AlignCenter, AlignLeft, AlignRight, Bold, Eraser, Heading2, Heading3, Italic, Link2, List, ListOrdered, Quote, Redo2, Table2, Underline, Undo2 } from 'lucide-react';
import { useEffect } from 'react';

function Btn({ label, on, active, disabled, children }: { label: string; on: () => void; active?: boolean; disabled?: boolean; children: React.ReactNode }) {
    return (
        <button type="button" title={label} aria-label={label} aria-pressed={active} disabled={disabled} onMouseDown={(e) => e.preventDefault()} onClick={on} className={`rounded-md p-1.5 text-slate-600 transition hover:bg-slate-100 disabled:opacity-30 ${active ? 'bg-fuchsia-100 text-fuchsia-800' : ''}`}>
            {children}
        </button>
    );
}

function Toolbar({ editor }: { editor: Editor }) {
    const { t } = useI18n();
    const link = () => {
        const prev = editor.getAttributes('link').href as string | undefined;
        const url = window.prompt(t('Link address (https://…)'), prev ?? 'https://');
        if (url === null) return;
        if (url === '') return void editor.chain().focus().extendMarkRange('link').unsetLink().run();
        if (!/^(https?:\/\/|mailto:|tel:)/i.test(url)) return void window.alert(t('Links must start with https://, http://, mailto: or tel:'));
        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    };
    const sep = <span className="mx-1 h-5 w-px bg-slate-200" aria-hidden />;

    return (
        <div role="toolbar" aria-label={t('Text formatting')} className="flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50 p-1.5">
            <Btn label={t('Bold')} active={editor.isActive('bold')} on={() => editor.chain().focus().toggleBold().run()}><Bold className="h-4 w-4" /></Btn>
            <Btn label={t('Italic')} active={editor.isActive('italic')} on={() => editor.chain().focus().toggleItalic().run()}><Italic className="h-4 w-4" /></Btn>
            <Btn label={t('Underline')} active={editor.isActive('underline')} on={() => editor.chain().focus().toggleUnderline().run()}><Underline className="h-4 w-4" /></Btn>
            {sep}
            <Btn label={t('Heading')} active={editor.isActive('heading', { level: 2 })} on={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}><Heading2 className="h-4 w-4" /></Btn>
            <Btn label={t('Subheading')} active={editor.isActive('heading', { level: 3 })} on={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}><Heading3 className="h-4 w-4" /></Btn>
            {sep}
            <Btn label={t('Bulleted list')} active={editor.isActive('bulletList')} on={() => editor.chain().focus().toggleBulletList().run()}><List className="h-4 w-4" /></Btn>
            <Btn label={t('Numbered list')} active={editor.isActive('orderedList')} on={() => editor.chain().focus().toggleOrderedList().run()}><ListOrdered className="h-4 w-4" /></Btn>
            <Btn label={t('Quote')} active={editor.isActive('blockquote')} on={() => editor.chain().focus().toggleBlockquote().run()}><Quote className="h-4 w-4" /></Btn>
            {sep}
            <Btn label={t('Align left')} active={editor.isActive({ textAlign: 'left' })} on={() => editor.chain().focus().setTextAlign('left').run()}><AlignLeft className="h-4 w-4" /></Btn>
            <Btn label={t('Align centre')} active={editor.isActive({ textAlign: 'center' })} on={() => editor.chain().focus().setTextAlign('center').run()}><AlignCenter className="h-4 w-4" /></Btn>
            <Btn label={t('Align right')} active={editor.isActive({ textAlign: 'right' })} on={() => editor.chain().focus().setTextAlign('right').run()}><AlignRight className="h-4 w-4" /></Btn>
            {sep}
            <Btn label={t('Link')} active={editor.isActive('link')} on={link}><Link2 className="h-4 w-4" /></Btn>
            <Btn label={t('Insert table')} on={() => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()}><Table2 className="h-4 w-4" /></Btn>
            <Btn label={t('Clear formatting')} on={() => editor.chain().focus().clearNodes().unsetAllMarks().run()}><Eraser className="h-4 w-4" /></Btn>
            {sep}
            <Btn label={t('Undo')} disabled={!editor.can().undo()} on={() => editor.chain().focus().undo().run()}><Undo2 className="h-4 w-4" /></Btn>
            <Btn label={t('Redo')} disabled={!editor.can().redo()} on={() => editor.chain().focus().redo().run()}><Redo2 className="h-4 w-4" /></Btn>
        </div>
    );
}

/** Formatted text for long messages: bold, lists, links, tables and so on. The server cleans what it receives, so only safe markup is ever stored. */
export function RichEditor({ value, onChange, label, placeholder, error, minHeight = 160 }: { value: string; onChange: (html: string) => void; label: string; placeholder?: string; error?: string; minHeight?: number }) {
    const { t } = useI18n();
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3] }, link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' } } }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Table.configure({ resizable: false }),
            TableRow,
            TableHeader,
            TableCell,
        ],
        content: value,
        editorProps: { attributes: { class: 'rich tiptap-body focus:outline-none', 'aria-label': label, role: 'textbox', 'aria-multiline': 'true', 'data-placeholder': placeholder ?? '' } },
        onUpdate: ({ editor: e }) => onChange(e.isEmpty ? '' : e.getHTML()),
        immediatelyRender: false,
    });

    // If the form is reset from outside, clear the editor too.
    useEffect(() => {
        if (editor && value === '' && !editor.isEmpty) editor.commands.clearContent();
    }, [value, editor]);

    return (
        <div>
            <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
            <div className={`overflow-hidden rounded-xl border bg-white focus-within:border-fuchsia-500 ${error ? 'border-rose-400' : 'border-slate-200'}`}>
                {editor ? <Toolbar editor={editor} /> : <div className="h-10 border-b border-slate-200 bg-slate-50" />}
                <div style={{ minHeight }} className="cursor-text px-4 py-3 text-sm" onClick={() => editor?.chain().focus().run()}>
                    <EditorContent editor={editor} />
                </div>
            </div>
            {error && <p role="alert" className="mt-1 text-xs text-rose-600">{t(error)}</p>}
        </div>
    );
}

/** Shows text that was written in the editor. The server has already cleaned it. */

export { Rich } from './rich-html';
