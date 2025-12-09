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
                new_name = name.replace(']', '')
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
                    this.input_file.is_compiled()
                    //
                 }, 500)
                // stop upload
                if (this.xhr[group_i]) this.xhr[group_i].abort()
            })
            liContainer2.appendChild(btn)
            ul.appendChild(li)
       
            this.uploadSingleFile(files[i], this.countI)
        }

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
                        if (groupItem) groupItem.remove();
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
    document.querySelectorAll('.js-file-uploader').forEach(function(el) {
        // Skip if this is an image uploader (will be handled by ImageUploader)
        const uploaderType = el.querySelector('.js-uploader-type');
        if (uploaderType && uploaderType.value === 'image') {
            return; // Skip image uploaders
        }
        new FileUploader(el);
    });
    // il bottone per rimuovere il campo file nel caso sia già presente un valore
    document.querySelectorAll('.js-upload-file-remove-exist-value').forEach(function(el) {
        el.addEventListener('click', (ev) => {
            let el = ev.currentTarget;
            let group = el.closest('.js-group-item');
            group.remove();
        
        });
    });
});
