import 'dropzone/dist/dropzone.css';
import '../css/uploader.css';
import Dropzone from 'dropzone';

Dropzone.autoDiscover = false;

class UploaderDropzone {
    constructor(options = {}) {
        this.options = {
            url: options.endpoint || 'index.php?page=uploader/endpoint',
            paramName: 'file',
            maxFilesize: options.maxFilesize || 256,
            acceptedFiles: options.acceptFileTypes || null,
            chunking: true,
            forceChunking: true,
            chunkSize: 5000000, // 5 MB
            parallelChunkUploads: true,
            retryChunks: true,
            retryChunksLimit: 3,
            maxFiles: options.maxFiles || null,
            hiddenInputContainer: 'body',
            previewTemplate: this.getPreviewTemplate(),
            // Verwende die übersetzten Meldungen aus rex.uploader.messages
            dictMaxFilesExceeded: rex.uploader.messages.maxNumberOfFiles,
            dictInvalidFileType: rex.uploader.messages.acceptFileTypes,
            dictFileTooBig: rex.uploader.messages.maxFileSize,
            dictFileTooSmall: rex.uploader.messages.minFileSize,
            dictResponseError: (file, message) => {
                if (message.error) return message.error;
                return message;
            },
            dictCancelUpload: rex.uploader.messages.abort,
            ...options
        };

        this.imageOptions = {
            maxWidth: options.imageMaxWidth || null,
            maxHeight: options.imageMaxHeight || null,
            quality: options.imageQuality || 0.8,
            resize: true
        };

        this.init();
    }

    init() {
        this.dropzone = new Dropzone('#uploader', {
            ...this.options,
            init: function() {
                this.on('addedfile', file => {
                    if (file.type.match(/image.*/) && (this.options.imageMaxWidth || this.options.imageMaxHeight)) {
                        this.processImageFile(file);
                    }
                });

                this.on('sending', (file, xhr, formData) => {
                    // CSRF Token hinzufügen falls vorhanden
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    if (csrfToken) {
                        formData.append('_csrf_token', csrfToken);
                    }

                    // Kategorie hinzufügen
                    const category = document.querySelector('#rex-mediapool-category')?.value;
                    if (category) {
                        formData.append('rex_file_category', category);
                    }
                });

                this.on('success', (file, response) => {
                    if (response.files?.[0]?.error) {
                        file.previewElement.classList.add('dz-error');
                        file.previewElement.querySelector('.dz-error-message').textContent = response.files[0].error;
                    }
                });
            },

            // Chunk Upload Handler
            chunksUploaded: (file, done) => {
                done();
            }
        });
    }

    processImageFile(file) {
        const img = new Image();
        img.onload = () => {
            const { width, height } = this.calculateDimensions(img, this.imageOptions);
            
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);
            
            canvas.toBlob(blob => {
                const newFile = new File([blob], file.name, {
                    type: file.type,
                    lastModified: file.lastModified
                });
                
                Object.assign(newFile, {
                    previewElement: file.previewElement,
                    previewTemplate: file.previewTemplate,
                    status: file.status
                });
                
                this.dropzone.removeFile(file);
                this.dropzone.addFile(newFile);
            }, file.type, this.imageOptions.quality);
        };
        
        const reader = new FileReader();
        reader.onload = e => img.src = e.target.result;
        reader.readAsDataURL(file);
    }

    calculateDimensions(img, options) {
        let { width, height } = img;
        const { maxWidth, maxHeight } = options;
        
        if (maxWidth && width > maxWidth) {
            height = Math.round((height * maxWidth) / width);
            width = maxWidth;
        }
        
        if (maxHeight && height > maxHeight) {
            width = Math.round((width * maxHeight) / height);
            height = maxHeight;
        }
        
        return { width, height };
    }

    getPreviewTemplate() {
        return `
            <div class="dz-preview dz-file-preview">
                <div class="dz-image">
                    <img data-dz-thumbnail />
                </div>
                <div class="dz-details">
                    <div class="dz-filename"><span data-dz-name></span></div>
                    <div class="dz-size" data-dz-size></div>
                </div>
                <div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>
                <div class="dz-success-mark"><span>✔</span></div>
                <div class="dz-error-mark"><span>✘</span></div>
                <div class="dz-error-message"><span data-dz-errormessage></span></div>
                <div class="dz-actions">
                    <button type="button" class="btn btn-xs" data-dz-remove>
                        <i class="rex-icon rex-icon-delete"></i>
                    </button>
                </div>
            </div>`;
    }
}

// Global init
document.addEventListener('rex:ready', () => {
    const uploaderElement = document.getElementById('uploader');
    if (uploaderElement) {
        const options = {
            endpoint: uploaderElement.dataset.endpoint,
            acceptFileTypes: uploaderElement.dataset.acceptedFiles,
            maxFilesize: parseInt(uploaderElement.dataset.maxFilesize, 10),
            imageMaxWidth: parseInt(uploaderElement.dataset.imageMaxWidth, 10),
            imageMaxHeight: parseInt(uploaderElement.dataset.imageMaxHeight, 10),
            dictDefaultMessage: uploaderElement.dataset.dictDefaultMessage,
            maxFiles: parseInt(uploaderElement.dataset.maxFiles, 10)
        };

        new UploaderDropzone(options);
    }
});