'use strict'
/**
 * Classe per gestire l'upload di file.
 * V 1.0
 */
class FileUploader {
    // container dell'elemento
    el_container = null;
    // URL di upload
    upload_url = ''
    xhr = []
    countI = 0
    max_size = 0
    max_files = 0
    input_file = null
    sortable_enabled = false
    sortable_instance = null
    constructor(el) {
        this.el_container = el
        this.upload_url = milk_url+"?page=upload-file-xhr"
        this.init()
    }

    /**
     * Nell'init vanno messi tutti i listener che si vogliono attaccare all'elemento
     * @returns void
     */
    init() {
        // scrivendo la funzione come arrow function posso usare this della classe all'interno della funzione
        this.el_container.querySelector('input[type="file"]').addEventListener('change', (ev) => {
            this.uploadFiles(ev.currentTarget.files)
        });
        this.input_file =  this.el_container.querySelector('input[type="file"]');
        // Appendo a file una funzione che ritorna se il campo è compilato o meno e gestisce la validità del campo
        this.input_file.is_compiled = () => {
            // this.el_container.style.border = '1px solid red';
            let is_compiled = (this.el_container.querySelectorAll('.js-file-name').length > 0)
            if (this.input_file.classList.contains('js-upload-required')) {
                if (is_compiled) {
                    this.input_file.setCustomValidity('')
                } else {
                    this.input_file.setCustomValidity('File is required')
                }
            }
            return is_compiled;
        } 
        if (this.input_file.hasAttribute('required')) {
            this.input_file.removeAttribute('required');
            this.input_file.classList.add('js-upload-required');
            this.input_file.is_compiled()
          
        }

        this.max_size = parseInt(this.el_container.querySelector('input[type="file"]').getAttribute('max-size')) || 0;
        // Get max files from hidden field
        const maxFilesEl = this.el_container.querySelector('.js-max-files');
        this.max_files = maxFilesEl ? parseInt(maxFilesEl.value) || 0 : 0;

        // Start from the highest existing index to avoid collisions on edit forms
        this.countI = this.getMaxExistingIndex();

        const sortableEnabledEl = this.el_container.querySelector('.js-sortable-enabled');
        this.sortable_enabled = sortableEnabledEl
            ? ['1', 'true', 'yes', 'on'].includes(String(sortableEnabledEl.value || '').toLowerCase())
            : false;

        if (this.sortable_enabled) {
            this.setupSortable();
            this.reindexInputs();
        } else {
            this.refreshSortableHandles();
        }
    }

    /**
     * Get highest numeric index already used by hidden file inputs.
     * Supports names like data[field_files][3][url].
     * @returns {number}
     */
    getMaxExistingIndex() {
        let maxIndex = 0;
        this.el_container.querySelectorAll('.js-file-name').forEach((input) => {
            const inputName = input.getAttribute('name') || '';
            const match = inputName.match(/\[(\d+)\]\[(url|name|existing)\]$/);
            if (!match) {
                return;
            }
            const idx = parseInt(match[1], 10);
            if (!Number.isNaN(idx) && idx > maxIndex) {
                maxIndex = idx;
            }
        });
        return maxIndex;
    }

    /**
     * Enable sortable mode with ItoSortableList if configured.
     */
    setupSortable() {
        const list = this.el_container.querySelector('.js-file-uploader__list');
        if (!list) {
            return;
        }
        if (typeof ItoSortableList === 'undefined') {
            console.warn('ItoSortableList is not available, sortable upload disabled.');
            return;
        }
        this.sortable_instance = new ItoSortableList(list, {
            handleSelector: '.js-upload-sort-handle',
            onUpdate: () => this.reindexInputs()
        });
        this.refreshSortableHandles();
    }

    /**
     * Show/hide drag handles based on sortable setting.
     */
    refreshSortableHandles() {
        this.el_container.querySelectorAll('.js-upload-sort-handle').forEach((handle) => {
            if (this.sortable_enabled) {
                handle.classList.remove('d-none');
                handle.style.cursor = 'grab';
            } else {
                handle.classList.add('d-none');
            }
        });
    }

    /**
     * Reindex hidden inputs based on current visual order.
     */
    reindexInputs() {
        const list = this.el_container.querySelector('.js-file-uploader__list');
        if (!list) {
            return;
        }

        let index = 1;
        list.querySelectorAll('li').forEach((item) => {
            const urlInput = item.querySelector('input[name$="[url]"]');
            const nameInput = item.querySelector('input[name$="[name]"]');
            const existingInput = item.querySelector('input[name$="[existing]"]');

            if (!urlInput || !nameInput) {
                return;
            }

            this.renameIndexedInput(urlInput, index, 'url');
            this.renameIndexedInput(nameInput, index, 'name');
            if (existingInput) {
                this.renameIndexedInput(existingInput, index, 'existing');
            }
            index++;
        });

        this.countI = Math.max(this.countI, index - 1);
        if (this.input_file && typeof this.input_file.is_compiled === 'function') {
            this.input_file.is_compiled();
        }
    }

    /**
     * Rename input array index preserving field suffix.
     */
    renameIndexedInput(input, index, fieldKey) {
        const currentName = input.getAttribute('name') || '';
        const updatedName = currentName.replace(/\[\d+\]\[(url|name|existing)\]$/, `[${index}][${fieldKey}]`);
        if (updatedName !== currentName) {
            input.setAttribute('name', updatedName);
        }
    }

    

    /**
     * Funzione per l'upload dei file
     * @param {FileList} files - Lista dei file selezionati
     */
    uploadFiles(files) {

        // disegno la lista dei file
        const list = this.el_container.querySelector('.js-file-uploader__list');
        // verifico se il campo file è multiplo
        if (!this.el_container.querySelector('input[type="file"]').multiple) {
            // se non è multiplo svuoto la lista
            list.innerHTML = ''
        }
        
        // Check max files limit
        if (this.max_files > 0) {
            const currentFilesCount = this.el_container.querySelectorAll('.js-file-name').length;
            const newFilesCount = files.length;
            
            if (currentFilesCount + newFilesCount > this.max_files) {
                alert(`Maximum ${this.max_files} files allowed. Currently ${currentFilesCount} files uploaded.`);
                return;
            }
        }

        const ul =  this.el_container.querySelector('.js-file-uploader__list');
        for (let i = 0; i < files.length; i++) {
            this.countI++
            let li = eI('<li class="list-group-item d-flex justify-content-between align-items-start js-groupitem'+this.countI+'"></li>')
            let dragHandle = eI('<div class="my-2 me-2 text-body-secondary js-upload-sort-handle d-none" title="Drag to reorder" style="cursor: grab; user-select: none;"><i class="bi bi-grip-vertical"></i></div>')
            li.appendChild(dragHandle)
            let input;
            let liContainer1 = eI('<div class="me-2 w-100"></div>')
            li.appendChild(liContainer1)
            liContainer1.appendChild(eI('<div>'+files[i].name+'</div>'))

            let progress = eI('<div class="progress"><div class="progress-bar  js-progressbar'+this.countI+'" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div></div>')
            liContainer1.appendChild(progress)
            // trovo il nome della form file
            let name = this.el_container.querySelector('input[type="file"]').name;

            // Create inputs for new indexed structure
            if (name.includes('[')) {
                let new_name = name.replace(']', '')
                input = eI('<input type="hidden" class="js-file-name js-filename'+this.countI+'" name="'+new_name+'_files]['+this.countI+'][url]" value="'+files[i].name+'">')
                liContainer1.appendChild(input)
                input = eI('<input type="hidden" class="js-fileoriginalname'+this.countI+'" name="'+new_name+'_files]['+this.countI+'][name]" value="'+files[i].name+'">')
            liContainer1.appendChild(input)
            } else {
                input = eI('<input type="hidden" class="js-file-name js-filename'+this.countI+'" name="'+name+'_files['+this.countI+'][url]" value="'+files[i].name+'">')
                liContainer1.appendChild(input)
                input = eI('<input type="hidden" class="js-fileoriginalname'+this.countI+'" name="'+name+'_files['+this.countI+'][name]" value="'+files[i].name+'">')
                liContainer1.appendChild(input)
            }

            liContainer1.appendChild(eI('<div class="text-body-secondary d-none js-info'+this.countI+'"></div>'))

            let liContainer2 = eI('<div class="my-2 ms-1"></div>')
            li.appendChild(liContainer2)
            let btn = eI('<button type="button" class="btn-close" aria-label="Close"></button>')
            btn.__counti = this.countI
            btn.addEventListener('click', (ev) => {
                let group_i = ev.currentTarget.__counti
                let group = this.el_container.querySelector('.js-groupitem'+group_i)
                //this.el_container.querySelector('.js-progressbar'+group_i).classList.add('bg-danger');
                group.classList.add('opacity-fadeout');
                setTimeout(() => { 
                    group.remove(); 
                    this.reindexInputs();
                 }, 500)
                // stop upload
                if (this.xhr[group_i]) this.xhr[group_i].abort()
            })
            liContainer2.appendChild(btn)
            ul.appendChild(li)

            if (this.sortable_enabled && this.sortable_instance && typeof this.sortable_instance.makeDraggable === 'function') {
                this.sortable_instance.makeDraggable(li);
            }
       
            this.uploadSingleFile(files[i], this.countI)
        }

        this.refreshSortableHandles();
        this.reindexInputs();

        // svuoto il campo file
        this.el_container.querySelector('input[type="file"]').value = '';
        
    }

    /**
     * Funzione per l'upload di un singolo file
     * @param {File} file - File da caricare
     */
    uploadSingleFile(file, curI) {
        // Debug: log file size and max size
        let max_size = this.max_size * 1024;
        //console.log('File size:', file.size, 'Max size:', max_size, 'File name:', file.name);
        if (max_size > 0 && file.size > max_size) {
            this.el_container.querySelector('.js-progressbar'+curI).classList.add('bg-danger');
            this.el_container.querySelector('.js-info'+curI).classList.remove('d-none');
            this.el_container.querySelector('.js-info'+curI).classList.remove('text-body-secondary');
            this.el_container.querySelector('.js-info'+curI).classList.add('text-danger');
            this.el_container.querySelector('.js-info'+curI).innerHTML = 'File too large (' + human_file_size(file.size) + ' > ' + human_file_size(max_size) + ')';
            this.el_container.querySelector('.js-progressbar'+curI).parentElement.remove() 
            setTimeout(() => {
                // Remove only the specific item, not its parent
                let groupItem = this.el_container.querySelector('.js-groupitem'+curI);
                if (groupItem) {
                    groupItem.classList.add('opacity-fadeout');
                    setTimeout(() => { 
                        if (groupItem) {
                            groupItem.remove();
                            this.reindexInputs();
                        }
                    }, 500);
                }
            }, 5000)
            return;
        }
        const formData = new FormData();
        formData.append('file', file);
        // find field js-file-uploader-name
        let name = this.el_container.querySelector('.js-file-uploader-name').value;
        formData.append('form-name', name);
        let token = this.el_container.querySelector('.js-file-token').value;
        formData.append('token', token);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const csrfTokenName = document.querySelector('meta[name="csrf-token-name"]')?.getAttribute('content') || 'csrf_token';

        if (csrfToken) {
            formData.append(csrfTokenName, csrfToken);
        }

        this.xhr[curI] = new XMLHttpRequest();
        this.xhr[curI].open('POST', this.upload_url);
        this.xhr[curI].upload.onprogress = (ev) => {
            if (ev.lengthComputable) {
                const percent = (ev.loaded / ev.total) * 100;
                //this.el_container.querySelector('.js-i'+curI).value = curI;
                this.el_container.querySelector('.js-progressbar'+curI).style.width = percent + '%';
            }
        };
        this.xhr[curI].onload = (ev) => {
            //console.log('upload completato');
            // prendo il json di risposta
            let response = JSON.parse(this.xhr[curI].responseText);
            if (response.success) {
                // file caricato con successo
                this.input_file.is_compiled()
                // imposto l'URL del file (che sarà il percorso finale)
                this.el_container.querySelector('.js-filename'+curI).value = response.file_name;
                // imposto il nome originale del file
                this.el_container.querySelector('.js-fileoriginalname'+curI).value = response.original_name;
                this.el_container.querySelector('.js-progressbar'+curI).classList.add('bg-success');
                
                setTimeout(() => {
                    // parent
                    this.el_container.querySelector('.js-progressbar'+curI).parentElement.classList.add('opacity-fadeout');
                    setTimeout(() => { 
                        this.el_container.querySelector('.js-progressbar'+curI).parentElement.remove() 
                        this.el_container.querySelector('.js-info'+curI).classList.remove('d-none');
                        this.el_container.querySelector('.js-info'+curI).innerHTML = 'File uploaded';

                    }, 500)
                  
                }, 2000)

            } else {
                this.el_container.querySelector('.js-progressbar'+curI).classList.add('bg-danger');
                this.el_container.querySelector('.js-info'+curI).classList.remove('d-none');
                this.el_container.querySelector('.js-info'+curI).classList.remove('text-body-secondary');
                this.el_container.querySelector('.js-info'+curI).classList.add('text-danger');
                this.el_container.querySelector('.js-info'+curI).innerHTML = response.msg;
            }

        };
        this.xhr[curI].send(formData);
    }
}

/**
 * Attacco il plugin a tutti gli elementi con la classe js_file_uploader
 * Escludo gli image uploader che hanno js-uploader-type=image
 */
document.addEventListener('DOMContentLoaded', function() {
    startFileUploader();
});

document.addEventListener('updateContainer', function() {
    startFileUploader();
});

function startFileUploader() {
    document.querySelectorAll('.js-file-uploader').forEach(function(el) {
        // Skip if this is an image uploader (will be handled by ImageUploader)
        const uploaderType = el.querySelector('.js-uploader-type');
        if (uploaderType && uploaderType.value === 'image') {
            return; // Skip image uploaders
        }
        if (el.__fileUploaderInitialized) {
            return;
        }
        el.__fileUploaderInitialized = true;
        el.__fileUploaderInstance = new FileUploader(el);
    });
    // il bottone per rimuovere il campo file nel caso sia già presente un valore
    document.querySelectorAll('.js-file-uploader:not(.js-image-uploader) .js-upload-file-remove-exist-value').forEach(function(el) {
        if (el.__fileRemoveHandlerBound) {
            return;
        }
        el.__fileRemoveHandlerBound = true;
        el.addEventListener('click', (ev) => {
            let el = ev.currentTarget;
            let group = el.closest('.js-group-item');
            if (!group) {
                return;
            }
            const container = group ? group.closest('.js-file-uploader') : null;
            group.remove();
            const uploader = container ? container.__fileUploaderInstance : null;
            if (uploader && typeof uploader.reindexInputs === 'function') {
                uploader.reindexInputs();
            }
        
        });
    });
}
