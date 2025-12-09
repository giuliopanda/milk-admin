'use strict'
/**
 * Class for handling image uploads with preview
 * Based on FileUploader but with image preview capabilities
 * V 1.0
 */
class ImageUploader {
    // container element
    el_container = null;
    // Upload URL
    upload_url = ''
    xhr = []
    countI = 0
    max_size = 0
    max_files = 0
    input_file = null
    preview_size = 150

    constructor(el) {
        this.el_container = el
        this.upload_url = milk_url+"?page=upload-file-xhr"
        this.init()
    }

    /**
     * Initialize all event listeners
     * @returns void
     */
    init() {
        // Use arrow function to maintain class context
        this.el_container.querySelector('input[type="file"]').addEventListener('change', (ev) => {
            this.uploadFiles(ev.currentTarget.files)
        });
        this.input_file = this.el_container.querySelector('input[type="file"]');

        // Get preview size
        const previewSizeEl = this.el_container.querySelector('.js-preview-size');
        this.preview_size = previewSizeEl ? parseInt(previewSizeEl.value) || 150 : 150;

        // Attach validation function
        this.input_file.is_compiled = () => {
            let is_compiled = (this.el_container.querySelectorAll('.js-file-name').length > 0)
            if (this.input_file.classList.contains('js-upload-required')) {
                if (is_compiled) {
                    this.input_file.setCustomValidity('')
                } else {
                    this.input_file.setCustomValidity('Image is required')
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
     * Upload multiple files
     * @param {FileList} files - List of selected files
     */
    uploadFiles(files) {
        // Get the image list container
        const list = this.el_container.querySelector('.js-image-uploader__list');

        // If not multiple, clear the list
        if (!this.el_container.querySelector('input[type="file"]').multiple) {
            list.innerHTML = ''
        }

        // Check max files limit
        if (this.max_files > 0) {
            const currentFilesCount = this.el_container.querySelectorAll('.js-file-name').length;
            const newFilesCount = files.length;

            if (currentFilesCount + newFilesCount > this.max_files) {
                alert(`Maximum ${this.max_files} images allowed. Currently ${currentFilesCount} images uploaded.`);
                return;
            }
        }

        const ul = this.el_container.querySelector('.js-image-uploader__list');
        const thumbSize = 50; // Fixed thumbnail size

        for (let i = 0; i < files.length; i++) {
            // Validate file is an image
            if (!files[i].type.startsWith('image/')) {
                alert(`File ${files[i].name} is not an image. Only image files are allowed.`);
                continue;
            }

            this.countI++

            // Create list item
            let li = eI(`<li class="list-group-item d-flex justify-content-between align-items-center js-groupitem${this.countI}"></li>`);

            // Image column
            let imageCol = eI(`<div style="flex-shrink: 0; margin-right: 1rem;"></div>`);
            let img = eI(`<img class="js-image-preview${this.countI}" alt="${files[i].name}" style="width: ${thumbSize}px; height: ${thumbSize}px; object-fit: cover; border-radius: 4px;">`);

            let inputUrl, inputName;
            
            // Generate preview from file
            const reader = new FileReader();
            reader.onload = (e) => {
                img.src = e.target.result;
            };
            reader.readAsDataURL(files[i]);

            imageCol.appendChild(img);
            li.appendChild(imageCol);

            // Info column (file name + progress)
            let liContainer1 = eI(`<div class="me-2 w-100"></div>`);
            liContainer1.appendChild(eI(`<div>${files[i].name}</div>`));

            // Progress bar
            let progress = eI(`<div class="progress"><div class="progress-bar js-progressbar${this.countI}" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div></div>`);
            liContainer1.appendChild(progress);

            // Find form field name
            let name = this.el_container.querySelector('input[type="file"]').name;


             if (name.includes('[')) {
                let new_name = name.replace(']', '')
                console.log (new_name);
                inputUrl = eI(`<input type="hidden" class="js-file-name js-filename${this.countI}" name="${new_name}_files][${this.countI}][url]" value="${files[i].name}">`);
                liContainer1.appendChild(inputUrl);

                inputName = eI(`<input type="hidden" class="js-fileoriginalname${this.countI}" name="${new_name}_files][${this.countI}][name]" value="${files[i].name}">`);
                liContainer1.appendChild(inputName);
            } else {
                // Create hidden inputs for indexed structure
                inputUrl = eI(`<input type="hidden" class="js-file-name js-filename${this.countI}" name="${name}_files[${this.countI}][url]" value="${files[i].name}">`);
                liContainer1.appendChild(inputUrl);

                inputName = eI(`<input type="hidden" class="js-fileoriginalname${this.countI}" name="${name}_files[${this.countI}][name]" value="${files[i].name}">`);
                liContainer1.appendChild(inputName);
            }

           

            // Info message
            liContainer1.appendChild(eI(`<div class="text-body-secondary d-none js-info${this.countI}"></div>`));

            li.appendChild(liContainer1);

            // Remove button column
            let liContainer2 = eI(`<div class="my-2 ms-1"></div>`);
            let removeBtn = eI('<button type="button" class="btn-close" aria-label="Close"></button>');
            removeBtn.__counti = this.countI;
            removeBtn.addEventListener('click', (ev) => {
                let group_i = ev.currentTarget.__counti;
                let group = this.el_container.querySelector('.js-groupitem'+group_i);
                group.classList.add('opacity-fadeout');
                setTimeout(() => {
                    group.remove();
                    this.input_file.is_compiled();
                }, 500);
                // Stop upload if in progress
                if (this.xhr[group_i]) this.xhr[group_i].abort();
            });
            liContainer2.appendChild(removeBtn);
            li.appendChild(liContainer2);

            ul.appendChild(li);

            this.uploadSingleFile(files[i], this.countI);
        }

        // Clear file input
        this.el_container.querySelector('input[type="file"]').value = '';
    }

    /**
     * Upload a single file
     * @param {File} file - File to upload
     */
    uploadSingleFile(file, curI) {
        // Check file size
        let max_size = this.max_size * 1024;
        if (max_size > 0 && file.size > max_size) {
            this.el_container.querySelector('.js-progressbar'+curI).classList.add('bg-danger');
            this.el_container.querySelector('.js-info'+curI).classList.remove('d-none');
            this.el_container.querySelector('.js-info'+curI).classList.remove('text-body-secondary');
            this.el_container.querySelector('.js-info'+curI).classList.add('text-danger');
            this.el_container.querySelector('.js-info'+curI).innerHTML = 'File too large (' + human_file_size(file.size) + ' > ' + human_file_size(max_size) + ')';
            this.el_container.querySelector('.js-progressbar'+curI).parentElement.remove();
            setTimeout(() => {
                let groupItem = this.el_container.querySelector('.js-groupitem'+curI);
                if (groupItem) {
                    groupItem.classList.add('opacity-fadeout');
                    setTimeout(() => {
                        if (groupItem) groupItem.remove();
                    }, 500);
                }
            }, 5000);
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        // Find uploader name field
        let name = this.el_container.querySelector('.js-file-uploader-name').value;
        formData.append('form-name', name);

        let token = this.el_container.querySelector('.js-file-token').value;
        formData.append('token', token);

        // Add upload directory
        let uploadDir = this.el_container.querySelector('.js-upload-dir')?.value;
        if (uploadDir) {
            formData.append('upload-dir', uploadDir);
        }

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
                this.el_container.querySelector('.js-progressbar'+curI).style.width = percent + '%';
            }
        };

        this.xhr[curI].onload = (ev) => {
            let response = JSON.parse(this.xhr[curI].responseText);
            if (response.success) {
                // File uploaded successfully
                this.input_file.is_compiled();

                // Set final file URL for form submission
                this.el_container.querySelector('.js-filename'+curI).value = response.file_name;
                this.el_container.querySelector('.js-fileoriginalname'+curI).value = response.original_name;

                // Update image preview with temporary path
                let imgPreview = this.el_container.querySelector('.js-image-preview'+curI);
                if (imgPreview && response.preview_path) {
                    // Use preview_path if provided, otherwise use file_name
                    let previewUrl = response.preview_path;
                    imgPreview.src = previewUrl;
                }

                this.el_container.querySelector('.js-progressbar'+curI).classList.add('bg-success');

                setTimeout(() => {
                    this.el_container.querySelector('.js-progressbar'+curI).parentElement.classList.add('opacity-fadeout');
                    setTimeout(() => {
                        this.el_container.querySelector('.js-progressbar'+curI).parentElement.remove();
                        this.el_container.querySelector('.js-info'+curI).classList.remove('d-none');
                        this.el_container.querySelector('.js-info'+curI).innerHTML = 'Image uploaded';
                    }, 500);
                }, 2000);

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
 * Attach plugin to all elements with js-image-uploader class
 * Check if element has js-uploader-type=image to avoid conflicts with file uploader
 */
document.addEventListener('DOMContentLoaded', function() {
    startImageUploader()
});


document.addEventListener('updateContainer', function(event) {
    startImageUploader()
});

function startImageUploader() {
    document.querySelectorAll('.js-image-uploader').forEach(function(el) {
        // Only initialize if this is specifically an image uploader
        const uploaderType = el.querySelector('.js-uploader-type');
        if (uploaderType && uploaderType.value === 'image') {
            new ImageUploader(el);
        }
    });

    // Remove button for existing images
    document.querySelectorAll('.js-image-uploader .js-upload-file-remove-exist-value').forEach(function(el) {
        el.addEventListener('click', (ev) => {
            let el = ev.currentTarget;
            let group = el.closest('.js-image-item');
            group.classList.add('opacity-fadeout');
            setTimeout(() => {
                group.remove();
            }, 500);
        });
    });
}