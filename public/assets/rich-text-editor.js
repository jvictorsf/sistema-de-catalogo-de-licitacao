import { Editor } from 'https://esm.sh/@tiptap/core@3';
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@3';
import Underline from 'https://esm.sh/@tiptap/extension-underline@3';
import TextAlign from 'https://esm.sh/@tiptap/extension-text-align@3';
import Link from 'https://esm.sh/@tiptap/extension-link@3';
import { TableKit } from 'https://esm.sh/@tiptap/extension-table@3';

const editorRegistry = new WeakMap();

function loadEditorDefaults() {
    const fallback = {
        default_text_align: 'justify',
        force_text_alignment: true,
        font_css: 'Arial, Helvetica, sans-serif',
        font_size_pt: 12,
        line_height: 1.5,
        paragraph_spacing_pt: 6,
    };
    const source = document.getElementById('rich-text-editor-defaults');

    if (!source) {
        return fallback;
    }

    try {
        return { ...fallback, ...JSON.parse(source.textContent || '{}') };
    } catch (error) {
        console.warn('Não foi possível carregar os padrões do editor.', error);
        return fallback;
    }
}

const editorDefaults = loadEditorDefaults();

function applyEditorDefaults(component) {
    component.dataset.richForceAlignment = editorDefaults.force_text_alignment ? '1' : '0';
    component.style.setProperty('--rich-text-align', editorDefaults.default_text_align);
    component.style.setProperty('--rich-text-font-family', editorDefaults.font_css);
    component.style.setProperty('--rich-text-font-size', `${editorDefaults.font_size_pt}pt`);
    component.style.setProperty('--rich-text-line-height', editorDefaults.line_height);
    component.style.setProperty('--rich-text-paragraph-spacing', `${editorDefaults.paragraph_spacing_pt}pt`);
}

const button = (command, icon, label) => `
    <button type="button" class="rich-text-editor-button" data-editor-command="${command}" aria-label="${label}" title="${label}" aria-pressed="false">
        <i class="bi ${icon}" aria-hidden="true"></i>
    </button>`;

function toolbarMarkup() {
    return `
        <div class="rich-text-editor-toolbar" role="toolbar" aria-label="Formatação do texto">
            <div class="rich-text-editor-group">
                <label class="visually-hidden" data-block-label>Tipo de texto</label>
                <select class="rich-text-editor-select" data-editor-block aria-label="Tipo de texto" title="Tipo de texto">
                    <option value="paragraph">Parágrafo</option>
                    <option value="heading-1">Título 1</option>
                    <option value="heading-2">Título 2</option>
                    <option value="heading-3">Título 3</option>
                </select>
            </div>
            <div class="rich-text-editor-group" role="group" aria-label="Ênfase">
                ${button('bold', 'bi-type-bold', 'Negrito')}
                ${button('italic', 'bi-type-italic', 'Itálico')}
                ${button('underline', 'bi-type-underline', 'Sublinhado')}
            </div>
            <div class="rich-text-editor-group" role="group" aria-label="Listas">
                ${button('bullet-list', 'bi-list-ul', 'Lista com marcadores')}
                ${button('ordered-list', 'bi-list-ol', 'Lista numerada')}
            </div>
            <div class="rich-text-editor-group" role="group" aria-label="Alinhamento">
                ${button('align-left', 'bi-text-left', 'Alinhar à esquerda')}
                ${button('align-center', 'bi-text-center', 'Centralizar')}
                ${button('align-right', 'bi-text-right', 'Alinhar à direita')}
                ${button('align-justify', 'bi-justify', 'Justificar')}
            </div>
            <div class="rich-text-editor-group" role="group" aria-label="Links">
                ${button('link', 'bi-link-45deg', 'Adicionar ou editar link')}
                ${button('unlink', 'bi-link-45deg rich-text-editor-unlink-icon', 'Remover link')}
            </div>
            <div class="rich-text-editor-group">
                <select class="rich-text-editor-select" data-editor-table aria-label="Ações da tabela" title="Ações da tabela">
                    <option value="">Tabela</option>
                    <option value="insert">Inserir tabela 3 x 3</option>
                    <option value="add-row-before">Adicionar linha antes</option>
                    <option value="add-row-after">Adicionar linha depois</option>
                    <option value="delete-row">Excluir linha</option>
                    <option value="add-column-before">Adicionar coluna antes</option>
                    <option value="add-column-after">Adicionar coluna depois</option>
                    <option value="delete-column">Excluir coluna</option>
                    <option value="toggle-header-row">Alternar cabeçalho</option>
                    <option value="delete-table">Excluir tabela</option>
                </select>
            </div>
            <div class="rich-text-editor-group" role="group" aria-label="Histórico e limpeza">
                ${button('undo', 'bi-arrow-counterclockwise', 'Desfazer')}
                ${button('redo', 'bi-arrow-clockwise', 'Refazer')}
                ${button('clear-formatting', 'bi-eraser', 'Limpar formatação')}
            </div>
        </div>`;
}

function ensureComponent(textarea) {
    let component = textarea.closest('[data-rich-text-component]');

    if (component) {
        return component;
    }

    component = document.createElement('div');
    component.className = 'rich-text-editor-component';
    component.dataset.richTextComponent = '';
    component.dataset.richRequired = textarea.required ? '1' : '0';
    component.dataset.richMaxLength = textarea.dataset.richMaxLength || '50000';
    textarea.parentNode.insertBefore(component, textarea);
    component.appendChild(textarea);

    const error = document.createElement('div');
    error.className = 'invalid-feedback';
    error.dataset.richEditorError = '';
    component.appendChild(error);

    const meta = document.createElement('div');
    meta.className = 'rich-text-editor-meta';
    meta.innerHTML = '<span class="visually-hidden" data-rich-editor-status aria-live="polite"></span><span data-rich-editor-count aria-live="polite"></span>';
    component.appendChild(meta);

    return component;
}

function editorInitialContent(component, textarea) {
    const template = component.querySelector('template[data-rich-editor-initial]');

    if (template && template.innerHTML.trim() !== '') {
        return template.innerHTML;
    }

    const value = textarea.value.trim();

    if (value === '') {
        return '<p></p>';
    }

    if (/<(?:p|h[1-3]|ul|ol|table|blockquote|strong|em|u|a)\b/i.test(value)) {
        return value;
    }

    return value
        .split(/\r?\n/)
        .filter((line) => line.trim() !== '')
        .map((line) => `<p>${line.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')}</p>`)
        .join('');
}

function currentRequired(component, textarea) {
    if (component.dataset.richRequired === '1') {
        return true;
    }

    const section = component.closest('[data-section-row]');
    const requiredToggle = section?.querySelector('input[type="checkbox"][name$="[required]"]');

    return Boolean(requiredToggle?.checked || textarea.dataset.richRequired === '1');
}

function validateEditor(editor, component, textarea) {
    const textLength = editor.getText().trim().length;
    const maxLength = Number(component.dataset.richMaxLength || textarea.dataset.richMaxLength || 50000);
    const error = component.querySelector('[data-rich-editor-error]');
    const count = component.querySelector('[data-rich-editor-count]');
    let message = '';

    if (currentRequired(component, textarea) && textLength === 0) {
        message = 'Preencha este conteúdo.';
    } else if (textLength > maxLength) {
        message = `O conteúdo excede o limite de ${maxLength.toLocaleString('pt-BR')} caracteres.`;
    }

    component.classList.toggle('is-invalid', message !== '');
    editor.view.dom.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
    textarea.setCustomValidity(message);

    if (error) {
        error.textContent = message;
    }

    if (count) {
        count.textContent = `${textLength.toLocaleString('pt-BR')} / ${maxLength.toLocaleString('pt-BR')}`;
    }

    return message === '';
}

function syncEditor(editor, component, textarea) {
    textarea.value = editor.isEmpty ? '' : editor.getHTML();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    validateEditor(editor, component, textarea);
}

function updateToolbar(editor, toolbar) {
    const activeCommands = {
        bold: editor.isActive('bold'),
        italic: editor.isActive('italic'),
        underline: editor.isActive('underline'),
        'bullet-list': editor.isActive('bulletList'),
        'ordered-list': editor.isActive('orderedList'),
        'align-left': editor.isActive({ textAlign: 'left' }),
        'align-center': editor.isActive({ textAlign: 'center' }),
        'align-right': editor.isActive({ textAlign: 'right' }),
        'align-justify': editor.isActive({ textAlign: 'justify' }),
        link: editor.isActive('link'),
    };

    toolbar.querySelectorAll('[data-editor-command]').forEach((control) => {
        const active = Boolean(activeCommands[control.dataset.editorCommand]);
        control.classList.toggle('is-active', active);
        control.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    const block = toolbar.querySelector('[data-editor-block]');
    if (block) {
        block.value = [1, 2, 3].find((level) => editor.isActive('heading', { level }))
            ? `heading-${[1, 2, 3].find((level) => editor.isActive('heading', { level }))}`
            : 'paragraph';
    }

    const insideTable = editor.isActive('table');
    toolbar.querySelectorAll('[data-editor-command="unlink"]').forEach((control) => {
        control.disabled = !editor.isActive('link');
    });
    toolbar.querySelectorAll('[data-editor-command="undo"]').forEach((control) => {
        control.disabled = !editor.can().chain().focus().undo().run();
    });
    toolbar.querySelectorAll('[data-editor-command="redo"]').forEach((control) => {
        control.disabled = !editor.can().chain().focus().redo().run();
    });

    const tableSelect = toolbar.querySelector('[data-editor-table]');
    if (tableSelect) {
        Array.from(tableSelect.options).forEach((option) => {
            if (option.value && option.value !== 'insert') {
                option.disabled = !insideTable;
            }
        });
    }
}

function runCommand(editor, command) {
    const chain = editor.chain().focus();

    switch (command) {
        case 'bold': chain.toggleBold().run(); break;
        case 'italic': chain.toggleItalic().run(); break;
        case 'underline': chain.toggleUnderline().run(); break;
        case 'bullet-list': chain.toggleBulletList().run(); break;
        case 'ordered-list': chain.toggleOrderedList().run(); break;
        case 'align-left': chain.setTextAlign('left').run(); break;
        case 'align-center': chain.setTextAlign('center').run(); break;
        case 'align-right': chain.setTextAlign('right').run(); break;
        case 'align-justify': chain.setTextAlign('justify').run(); break;
        case 'unlink': chain.unsetLink().run(); break;
        case 'undo': chain.undo().run(); break;
        case 'redo': chain.redo().run(); break;
        case 'clear-formatting': chain.unsetAllMarks().clearNodes().run(); break;
        case 'link': {
            const currentHref = editor.getAttributes('link').href || '';
            const href = window.prompt('Informe o endereço do link. Deixe vazio para remover.', currentHref);
            if (href === null) break;
            if (href.trim() === '') chain.unsetLink().run();
            else chain.extendMarkRange('link').setLink({ href: href.trim() }).run();
            break;
        }
    }
}

function runTableCommand(editor, command) {
    const chain = editor.chain().focus();

    switch (command) {
        case 'insert': chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(); break;
        case 'add-row-before': chain.addRowBefore().run(); break;
        case 'add-row-after': chain.addRowAfter().run(); break;
        case 'delete-row': chain.deleteRow().run(); break;
        case 'add-column-before': chain.addColumnBefore().run(); break;
        case 'add-column-after': chain.addColumnAfter().run(); break;
        case 'delete-column': chain.deleteColumn().run(); break;
        case 'toggle-header-row': chain.toggleHeaderRow().run(); break;
        case 'delete-table': chain.deleteTable().run(); break;
    }
}

function initializeEditor(textarea) {
    if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.richReady === '1' || textarea.disabled || textarea.readOnly) {
        return;
    }

    textarea.dataset.richReady = '1';
    const component = ensureComponent(textarea);
    applyEditorDefaults(component);
    const status = component.querySelector('[data-rich-editor-status]');
    const toolbar = document.createElement('div');
    const surface = document.createElement('div');
    toolbar.innerHTML = toolbarMarkup();
    const toolbarElement = toolbar.firstElementChild;
    surface.className = 'rich-text-editor-surface';
    component.insertBefore(toolbarElement, textarea);
    component.insertBefore(surface, textarea);

    const wasRequired = textarea.required;
    if (wasRequired) component.dataset.richRequired = '1';
    textarea.required = false;

    const editor = new Editor({
        element: surface,
        content: editorInitialContent(component, textarea),
        extensions: [
            StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
            Underline,
            TextAlign.configure({
                types: ['heading', 'paragraph'],
                defaultAlignment: editorDefaults.default_text_align,
            }),
            Link.configure({ openOnClick: false, autolink: true, defaultProtocol: 'https' }),
            TableKit.configure({ table: { resizable: true } }),
        ],
        editorProps: {
            attributes: {
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': textarea.dataset.richEditorLabel || 'Editor de texto rico',
                spellcheck: 'true',
            },
        },
        onCreate: ({ editor: currentEditor }) => {
            textarea.hidden = true;
            if (status) status.textContent = 'Editor carregado.';
            syncEditor(currentEditor, component, textarea);
            updateToolbar(currentEditor, toolbarElement);
        },
        onUpdate: ({ editor: currentEditor }) => syncEditor(currentEditor, component, textarea),
        onSelectionUpdate: ({ editor: currentEditor }) => updateToolbar(currentEditor, toolbarElement),
        onTransaction: ({ editor: currentEditor }) => updateToolbar(currentEditor, toolbarElement),
    });

    editorRegistry.set(textarea, editor);

    toolbarElement.addEventListener('click', (event) => {
        const control = event.target.closest('[data-editor-command]');
        if (!control || control.disabled) return;
        runCommand(editor, control.dataset.editorCommand);
    });

    toolbarElement.querySelector('[data-editor-block]')?.addEventListener('change', (event) => {
        const value = event.target.value;
        if (value === 'paragraph') editor.chain().focus().setParagraph().run();
        else editor.chain().focus().toggleHeading({ level: Number(value.split('-')[1]) }).run();
    });

    toolbarElement.querySelector('[data-editor-table]')?.addEventListener('change', (event) => {
        if (event.target.value) runTableCommand(editor, event.target.value);
        event.target.value = '';
    });
}

function scan(scope = document) {
    if (scope.matches?.('textarea[data-rich-editor]')) initializeEditor(scope);
    scope.querySelectorAll?.('textarea[data-rich-editor]').forEach(initializeEditor);
}

document.addEventListener('submit', (event) => {
    const editors = event.target.querySelectorAll?.('textarea[data-rich-editor]') || [];
    let firstInvalid = null;

    editors.forEach((textarea) => {
        const editor = editorRegistry.get(textarea);
        const component = textarea.closest('[data-rich-text-component]');
        if (!editor || !component) return;
        syncEditor(editor, component, textarea);
        if (!validateEditor(editor, component, textarea) && !firstInvalid) firstInvalid = editor;
    });

    if (firstInvalid) {
        event.preventDefault();
        firstInvalid.commands.focus();
    }
}, true);

const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
        if (node instanceof HTMLElement) scan(node);
    }));
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        scan(document);
        observer.observe(document.body, { childList: true, subtree: true });
    });
} else {
    scan(document);
    observer.observe(document.body, { childList: true, subtree: true });
}

window.RichTextEditor = {
    scan,
    getEditor: (textarea) => editorRegistry.get(textarea) || null,
};
