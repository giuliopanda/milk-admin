/**
 * TrixEditorManager class
 * Gestisce le istanze dell'editor Trix con funzionalità di toggle tra editor e textarea
 */

class TrixEditorManager {
    constructor() {
        this.instances = new Map();
        this.counter = 0;
        this.originalTextareas = new Map();
        
        // Configura Trix per usare h3 invece di h1
        this.configureTrixHeadings();
    }
    
    configureTrixHeadings() {
        // Aspetta che Trix sia caricato
        if (typeof Trix !== 'undefined') {
            // Modifica la configurazione di Trix per il tag heading1
            Trix.config.blockAttributes.heading1.tagName = "h3";
        } else {
            // Se Trix non è ancora caricato, riprova dopo un breve delay
            setTimeout(() => this.configureTrixHeadings(), 100);
        }
    }

    createEditor({
        containerId,
        name = '',
        value = '',
        placeholder = '',
        onChange = null,
        onBlur = null,
        onFocus = null,
        onFileAccept = null,
        onAttachmentAdd = null,
        onAttachmentRemove = null,
        toolbar = true,
        autofocus = false,
        height = '200px',
        enableToggle = true
    }) {
        this.counter++;
        const editorContainerId = `trix-container-${this.counter}`;
        const editorId = `trix-editor-${this.counter}`;
        const inputId = `trix-input-${this.counter}`;
        const toolbarId = `trix-toolbar-${this.counter}`;

        // Create container
        const container = document.createElement('div');
        container.id = editorContainerId;
        container.className = 'trix-container mb-3';

        // Create custom toolbar if needed
        if (toolbar && enableToggle) {
            const customToolbar = this.createCustomToolbar(toolbarId, containerId);
            container.appendChild(customToolbar);
        }

        // Create hidden input for Trix
        const hiddenInput =  document.createElement('textarea');
        hiddenInput.style.display = 'none';
        hiddenInput.id = inputId;
        hiddenInput.name = name;
        hiddenInput.value = value;

        // Create trix-editor element
        const trixEditor = document.createElement('trix-editor');
        trixEditor.id = editorId;
        trixEditor.setAttribute('input', inputId);
        
        if (toolbar && enableToggle) {
            trixEditor.setAttribute('toolbar', toolbarId);
        } else if (!toolbar) {
            trixEditor.setAttribute('toolbar', 'false');
        }
        
        if (placeholder) {
            trixEditor.setAttribute('placeholder', placeholder);
        }
        
        if (autofocus) {
            trixEditor.setAttribute('autofocus', '');
        }

        // Set custom styles
        trixEditor.style.minHeight = height;
        trixEditor.className = 'form-control mt-2';

        // Append elements
        container.appendChild(hiddenInput);
        container.appendChild(trixEditor);

        // Add to DOM
        const targetContainer = document.getElementById(containerId);
        if (!targetContainer) {
            console.error(`Container with ID '${containerId}' not found. Cannot create Trix editor.`);
            return null;
        }
        targetContainer.appendChild(container);

        // Wait for Trix to initialize
        trixEditor.addEventListener('trix-initialize', () => {
            const editor = trixEditor.editor;
            
            // Store instance
            this.instances.set(containerId, {
                editor: editor,
                trixElement: trixEditor,
                hiddenInput: hiddenInput,
                container: container,
                height: height,
                placeholder: placeholder,
                enableToggle: enableToggle,
                isTextarea: false,
                callbacks: {
                    onChange,
                    onBlur,
                    onFocus,
                    onFileAccept,
                    onAttachmentAdd,
                    onAttachmentRemove
                }
            });

            // Set initial value if provided
            if (value) {
                editor.loadHTML(value);
            }

            // Setup event listeners
            if (onChange) {
                trixEditor.addEventListener('trix-change', (event) => {
                    onChange(hiddenInput.value, event);
                });
            }

            if (onBlur) {
                trixEditor.addEventListener('trix-blur', (event) => {
                    onBlur(hiddenInput.value, event);
                });
            }

            if (onFocus) {
                trixEditor.addEventListener('trix-focus', (event) => {
                    onFocus(event);
                });
            }

            if (onFileAccept) {
                trixEditor.addEventListener('trix-file-accept', (event) => {
                    onFileAccept(event);
                });
            }

            if (onAttachmentAdd) {
                trixEditor.addEventListener('trix-attachment-add', (event) => {
                    onAttachmentAdd(event);
                });
            }

            if (onAttachmentRemove) {
                trixEditor.addEventListener('trix-attachment-remove', (event) => {
                    onAttachmentRemove(event);
                });
            }

            trixEditor.addEventListener("trix-attachment-add", function(event) {
                const attachment = event.attachment;
                
                // Se l'attachment ha già un URL, non fare nulla (è già stato caricato)
                if (attachment.getAttribute("url")) {
                    return;
                }
              
                if (attachment.file) {
                    const file = attachment.file;
                    const formData = new FormData();
                    formData.append("file", file);
                
                    fetch(milk_url + '?page=editor-upload-file', {
                    method: "POST",
                    body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                    // Supponendo che il server restituisca un JSON con la URL
                    attachment.setAttributes({
                        url: result.url,
                        href: result.url // opzionale
                    });
                    })
                    .catch(error => {
                        alert("Upload failed:", error);
                    });
                }
            });
        });

        return containerId;
    }

    createCustomToolbar(toolbarId, containerId) {
        const toolbar = document.createElement('trix-toolbar');
        toolbar.id = toolbarId;
        
        // Copia la toolbar standard di Trix
        toolbar.innerHTML = `
            <div class="trix-button-row">
                <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-bold" data-trix-attribute="bold" data-trix-key="b" title="Bold" tabindex="-1">Bold</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-italic" data-trix-attribute="italic" data-trix-key="i" title="Italic" tabindex="-1">Italic</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-strike" data-trix-attribute="strike" title="Strikethrough" tabindex="-1">Strikethrough</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-link" data-trix-attribute="href" data-trix-action="link" data-trix-key="k" title="Link" tabindex="-1">Link</button>
                   <button type="button" class="trix-button trix-button--icon trix-button--icon-heading-1" data-trix-attribute="heading1" title="Heading" tabindex="-1">Heading</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-quote" data-trix-attribute="quote" title="Quote" tabindex="-1">Quote</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-bullet-list" data-trix-attribute="bullet" title="Bullets" tabindex="-1">Bullets</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-number-list" data-trix-attribute="number" title="Numbers" tabindex="-1">Numbers</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-attach" data-trix-action="attachFiles" title="Attach Files" tabindex="-1">Attach Files</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-toggle-editor" 
                            data-action="toggle-editor" 
                            title="Toggle Editor" 
                            tabindex="-1">Toggle Editor</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-undo" data-trix-action="undo" data-trix-key="z" title="Undo" tabindex="-1">Undo</button>
                    <button type="button" class="trix-button trix-button--icon trix-button--icon-redo" data-trix-action="redo" data-trix-key="shift+z" title="Redo" tabindex="-1">Redo</button>
                </span>
            </div>
            <div class="trix-dialogs" data-trix-dialogs>
                <div class="trix-dialog trix-dialog--link" data-trix-dialog="href" data-trix-dialog-attribute="href">
                    <div class="trix-dialog__link-fields">
                        <input type="url" name="href" class="trix-input trix-input--dialog" placeholder="Enter a URL…" aria-label="URL" required data-trix-input>
                        <div class="trix-button-group">
                            <input type="button" class="trix-button trix-button--dialog" value="Link" data-trix-method="setAttribute">
                            <input type="button" class="trix-button trix-button--dialog" value="Unlink" data-trix-method="removeAttribute">
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Aggiungi event listener per il bottone toggle
        setTimeout(() => {
            const toggleButton = toolbar.querySelector('[data-action="toggle-editor"]');
            if (toggleButton) {
                toggleButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleEditorMode(containerId);
                });
            }
        }, 10);

        return toolbar;
    }

    toggleEditorMode(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance) return;

        if (instance.isTextarea) {
            this.switchToEditor(containerId);
        } else {
            this.switchToTextarea(containerId);
        }
    }

    // Formatta l'HTML per renderlo leggibile nella textarea
    formatHTMLForTextarea(html) {
        // Rimuovi spazi e newline extra per iniziare con HTML pulito
        let formattedHTML = html.replace(/>\s+</g, '><').trim();
        
        // Lista dei tag che dovrebbero avere un newline dopo
        const blockTags = ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'ul', 'ol', 'li', 'br'];
        
        // Aggiungi newline dopo i tag di chiusura dei blocchi
        blockTags.forEach(tag => {
            if (tag === 'br') {
                // Per <br> aggiungi newline dopo il tag
                formattedHTML = formattedHTML.replace(/<br\s*\/?>/gi, '<br>\n');
            } else {
                // Per altri tag aggiungi newline dopo il tag di chiusura
                const regex = new RegExp(`</${tag}>`, 'gi');
                formattedHTML = formattedHTML.replace(regex, `</${tag}>\n`);
            }
        });
        
        // Aggiungi newline prima dei tag di apertura dei blocchi (tranne se sono già preceduti da newline)
        blockTags.forEach(tag => {
            if (tag !== 'br') {
                const regex = new RegExp(`(?<!\\n)<${tag}(\\s[^>]*)?>`, 'gi');
                formattedHTML = formattedHTML.replace(regex, '\n<' + tag + '$1>');
            }
        });
        
        // Pulisci newline multipli
        formattedHTML = formattedHTML.replace(/\n{3,}/g, '\n\n');
        
        // Rimuovi newline all'inizio se presente
        formattedHTML = formattedHTML.replace(/^\n+/, '');
        
        return formattedHTML;
    }

    switchToTextarea(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance || instance.isTextarea) return;

        // Ottieni il valore HTML corrente e formattalo per la textarea
        const htmlValue = this.getValue(containerId);
        const formattedHTML = this.formatHTMLForTextarea(htmlValue);

        // Imposta il valore nella textarea
        instance.hiddenInput.value = formattedHTML;

        // Configura textarea
        instance.hiddenInput.className = 'form-control';
        instance.hiddenInput.style.minHeight = instance.height;
        
        if (instance.placeholder) {
            instance.hiddenInput.placeholder = instance.placeholder;
        }

        // Nascondi editor e mostra textarea
        instance.trixElement.style.display = 'none';
        instance.hiddenInput.style.display = 'block';
        
        instance.isTextarea = true;

        // Disabilita tutti i bottoni tranne quello di toggle
        const toolbar = instance.container.querySelector('trix-toolbar');
        if (toolbar) {
            toolbar.querySelectorAll('.trix-button').forEach((button) => {
                if (button != instance.container.querySelector('[data-action="toggle-editor"]')) {
                    button.disabled = true;
                    button.classList.remove('trix-active');
                    button.removeAttribute('data-trix-active');
                }
            });
        }

        // Aggiorna il bottone toggle
        const toggleButton = instance.container.querySelector('[data-action="toggle-editor"]');
        if (toggleButton) {
            toggleButton.title = 'Torna all\'editor';
            toggleButton.classList.add('trix-active');
        }
    }

    // Metodo per convertire HTML formattato dalla textarea a HTML valido per l'editor
    plainTextToHTML(text) {
        // Se il testo contiene già tag HTML, restituiscilo così com'è (pulito)
        if (text.includes('<') && text.includes('>')) {
            // Rimuovi newline extra e spazi tra i tag per pulire l'HTML
            return text.replace(/>\s+</g, '><').replace(/^\s+|\s+$/g, '');
        }
        
        // Se è testo semplice, convertilo in HTML come nel codice originale
        return text
            .split('\n\n')
            .map(paragraph => {
                if (paragraph.trim()) {
                    return `<div>${paragraph.replace(/\n/g, '<br>')}</div>`;
                }
                return '<div><br></div>';
            })
            .join('');
    }

    switchToEditor(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance || !instance.isTextarea) return;

        // Ottieni il valore dalla textarea
        const textValue = instance.hiddenInput.value;

        // Converti il testo in HTML
        const htmlValue = this.plainTextToHTML(textValue);

        // Aggiorna il valore nell'editor
        instance.editor.loadHTML(htmlValue);

        // Rimuovi textarea e mostra editor
        instance.trixElement.style.display = '';
        instance.hiddenInput.style.display = 'none';

        instance.isTextarea = false;
       
        // Riabilita tutti i bottoni
        const toolbar = instance.container.querySelector('trix-toolbar');
        if (toolbar) {
            toolbar.querySelectorAll('.trix-button').forEach((button) => {
                button.disabled = false;
            });
        }

        // Aggiorna il bottone toggle
        const toggleButton = instance.container.querySelector('[data-action="toggle-editor"]');
        if (toggleButton) {
            toggleButton.title = 'Mostra codice sorgente';
            toggleButton.classList.remove('trix-active');
        }
    }

    createFromTextarea(textareaId, options = {}) {
        const textarea = document.getElementById(textareaId);
        if (!textarea) {
            console.error(`Textarea with ID '${textareaId}' not found.`);
            return null;
        }

        // Store original textarea
        const textareaClone = textarea.cloneNode(true);
        this.originalTextareas.set(textareaId, textareaClone);

        // Get textarea attributes
        const value = textarea.value || '';
        const placeholder = textarea.getAttribute('placeholder') || '';
        const name = textarea.getAttribute('name') || '';

        // Create container div where textarea was
        const container = document.createElement('div');
        container.id = `trix-textarea-container-${textareaId}`;
        
        // Replace textarea with container
        textarea.parentNode.replaceChild(container, textarea);

        // Create editor with textarea's properties
        const editorId = this.createEditor({
            containerId: container.id,
            value: this.plainTextToHTML(value),
            placeholder: placeholder,
            ...options
        });

        // If textarea had a name, add it to the hidden input
        if (name && editorId) {
            const instance = this.instances.get(container.id);
            if (instance) {
                instance.hiddenInput.setAttribute('name', name);
            }
        }

        return container.id;
    }

    destroyAndRestoreTextarea(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance) return false;

        // Get current value
        const currentValue = this.getValue(containerId);

        // Find original textarea ID from container ID
        const textareaId = containerId.replace('trix-textarea-container-', '');
        const originalTextarea = this.originalTextareas.get(textareaId);

        if (originalTextarea) {
            // Update textarea value
            originalTextarea.value = this.getPlainText(containerId);
            
            // Replace container with original textarea
            instance.container.parentNode.replaceChild(originalTextarea, instance.container);
            
            // Clean up
            this.originalTextareas.delete(textareaId);
            this.instances.delete(containerId);
            
            return true;
        }

        return false;
    }

    getValue(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance) return null;
        
        if (instance.isTextarea) {
            return this.plainTextToHTML(instance.hiddenInput.value);
        }
        
        return instance.hiddenInput.value;
    }

    getPlainText(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance) return null;
        
        if (instance.isTextarea) {
            return instance.hiddenInput.value;
        }
        
        // Restituisci l'HTML formattato invece di convertirlo in testo
        const htmlValue = instance.hiddenInput.value;
        return this.formatHTMLForTextarea(htmlValue);
    }

    setValue(containerId, value) {
        const instance = this.instances.get(containerId);
        if (!instance) return;
        
        if (instance.isTextarea) {
            instance.hiddenInput.value = value;
        } else {
            instance.editor.loadHTML(value);
        }
    }

    insertText(containerId, text) {
        const instance = this.instances.get(containerId);
        if (!instance) return;
        
        if (instance.isTextarea) {
            const textarea = instance.hiddenInput;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const before = textarea.value.substring(0, start);
            const after = textarea.value.substring(end);
            textarea.value = before + text + after;
            textarea.selectionStart = textarea.selectionEnd = start + text.length;
        } else {
            instance.editor.insertString(text);
        }
    }

    insertHTML(containerId, html) {
        const instance = this.instances.get(containerId);
        if (!instance) return;
        
        if (instance.isTextarea) {
            // In modalità textarea, inserisci come testo semplice
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            this.insertText(containerId, tempDiv.textContent || tempDiv.innerText);
        } else {
            instance.editor.insertHTML(html);
        }
    }

    focus(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance) return;
        
        if (instance.isTextarea) {
            instance.hiddenInput.focus();
        } else {
            instance.trixElement.focus();
        }
    }

    blur(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance) return;
        
        if (instance.isTextarea) {
            instance.hiddenInput.blur();
        } else {
            instance.trixElement.blur();
        }
    }

    clear(containerId) {
        const instance = this.instances.get(containerId);
        if (!instance) return;
        
        if (instance.isTextarea) {
            instance.hiddenInput.value = '';
        } else {
            instance.editor.loadHTML('');
        }
    }

    setEnabled(containerId, enabled) {
        const instance = this.instances.get(containerId);
        if (!instance) return;
        
        if (instance.isTextarea) {
            instance.hiddenInput.disabled = !enabled;
        } else {
            if (enabled) {
                instance.trixElement.removeAttribute('contenteditable');
                instance.trixElement.removeAttribute('disabled');
            } else {
                instance.trixElement.setAttribute('contenteditable', 'false');
                instance.trixElement.setAttribute('disabled', 'disabled');
            }
        }
    }

    removeEditor(containerId) {
        const instance = this.instances.get(containerId);
        if (instance) {
            instance.container.remove();
            this.instances.delete(containerId);
        }
    }

    removeAll() {
        this.instances.forEach((instance, containerId) => {
            this.removeEditor(containerId);
        });
        this.originalTextareas.clear();
    }

    getAllInstances() {
        return Array.from(this.instances.keys());
    }
}

// Initialize and expose globally
window.editor = new TrixEditorManager();