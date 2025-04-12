<?php
if (rex::isBackend() && rex::getUser()) {
    if (!$this->getConfig('initialized', false)) {
        $this->setConfig('initialized', true);
        $this->setConfig('image-max-width', 4000);
        $this->setConfig('image-max-height', 4000);
        $this->setConfig('image-resize-checked', true);
    }

    rex_perm::register('uploader[]');
    rex_perm::register('uploader[page]');

    if (!rex::getUser()->hasPerm('uploader[page]')) {
        $page = $this->getProperty('page');
        $page['hidden'] = true;
        $this->setProperty('page', $page);
    }

    if ((rex_get('page') === 'mediapool/upload' || rex_get('page') === 'uploader/upload') 
        && rex::getUser()->hasPerm('uploader[]')) {
        
        // Übersetzte Fehlermeldungen für JavaScript
        rex_view::setJsProperty('uploader', [
            'messages' => [
                'maxNumberOfFiles' => rex_i18n::msg('uploader_errors_max_number_of_files'),
                'acceptFileTypes' => rex_i18n::msg('uploader_errors_accept_file_types'),
                'maxFileSize' => rex_i18n::msg('uploader_errors_max_file_size'),
                'minFileSize' => rex_i18n::msg('uploader_errors_min_file_size'),
                'maxWidth' => rex_i18n::msg('uploader_errors_max_width'),
                'minWidth' => rex_i18n::msg('uploader_errors_min_width'),
                'maxHeight' => rex_i18n::msg('uploader_errors_max_height'),
                'minHeight' => rex_i18n::msg('uploader_errors_min_height'),
                'imageResize' => rex_i18n::msg('uploader_errors_image_resize'),
                'abort' => rex_i18n::msg('uploader_errors_abort')
            ]
        ]);
        
        if ($this->getAssetsPath('uploader.min.js')) {
            // CSS vor JS laden
            rex_view::addCssFile($this->getAssetsUrl('uploader.min.css'));
            rex_view::addJsFile($this->getAssetsUrl('uploader.min.js'));
        }
    }
}

