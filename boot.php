<?php

use FriendsOfRedaxo\Uploader\BulkRework;
use FriendsOfRedaxo\Uploader\BulkReworkList;

$addon = rex_addon::get('uploader');

if (rex::isBackend() && rex::getUser()) {
    rex_perm::register('uploader[]');
    rex_perm::register('uploader[page]');

    if (!rex::getUser()->hasPerm('uploader[page]')) {
        $page = $this->getProperty('page');
        $page['hidden'] = 'true';
        $this->setProperty('page', $page);
    }
}

rex_extension::register('PACKAGES_INCLUDED', function () use ($addon) {
    if (rex::isBackend() && rex::getUser() && rex::getUser()->hasPerm('uploader[]')) {
        if (rex::isDebugMode() && rex_request_method() == 'get') {
            $compiler = new rex_scss_compiler();
            $compiler->setRootDir($this->getPath());
            $compiler->setScssFile($this->getPath('scss/uploader.scss'));
            $compiler->setCssFile($this->getPath('assets/uploader.css'));
            $compiler->compile();
            rex_file::copy($this->getPath('assets/uploader.css'), $this->getAssetsPath('uploader.css'));
            rex_file::copy($this->getPath('assets/uploader.js'), $this->getAssetsPath('uploader.js'));
        }
        $include_assets = 0;
        $include_template = 0;
        if (rex_get('page', 'string') == 'mediapool/upload' && $addon->getConfig('replace-mediapool-checked', true)) {
            $this->setProperty('context', 'mediapool_upload');
            $include_assets = 1;
            $include_template = 1;
        } elseif (rex_get('page', 'string') == 'mediapool/media') {
            $this->setProperty('context', 'mediapool_media');
            $include_assets = 1;
        } elseif (rex_get('page', 'string') == 'uploader/upload') {
            $this->setProperty('context', 'addon_upload');
            $include_assets = 1;
            $include_template = 1;
        }

        if ($include_assets) {

            rex_view::addJsFile($this->getAssetsUrl('vendor/JavaScript-Templates/js/tmpl.min.js'));
            rex_view::addJsFile($this->getAssetsUrl('vendor/JavaScript-Load-Image/js/load-image.all.min.js'));
            rex_view::addJsFile($this->getAssetsUrl('vendor/JavaScript-Canvas-to-Blob/js/canvas-to-blob.min.js'));
            rex_view::addJsFile($this->getAssetsUrl('vendor/jquery-file-upload/js/jquery.iframe-transport.js'));
            rex_view::addJsFile($this->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload.js'));
            rex_view::addJsFile($this->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-process.js'));
            rex_view::addJsFile($this->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-image.js'));
            //rex_view::addJsFile($this->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-audio.js'));
            //rex_view::addJsFile($this->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-video.js'));
            rex_view::addJsFile($this->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-validate.js'));
            rex_view::addJsFile($this->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-ui.js'));
            rex_view::addCssFile($this->getAssetsUrl('vendor/jquery-file-upload/css/jquery.fileupload.css'));
            rex_view::addCssFile($this->getAssetsUrl('vendor/jquery-file-upload/css/jquery.fileupload-ui.css'));
            rex_view::addCssFile($this->getAssetsUrl('uploader.css'));
            rex_view::addJsFile($this->getAssetsUrl('uploader.js'));
            rex_view::addJsFile($this->getAssetsUrl('image_resizer_standalone.js'));

            rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep) use ($include_template) {
                $vars = include(rex_path::addon('uploader') . 'inc/vars.php');
                $ep->setSubject(str_replace('</head>', $vars . '</head>', $ep->getSubject()));
                if ($include_template) {
                    $buttonbar_template = include(rex_path::addon('uploader') . 'inc/buttonbar.php');
                    $ep->setSubject(str_replace('</body>', $buttonbar_template . '</body>', $ep->getSubject()));
                    $file_list_templates = include(rex_path::addon('uploader') . 'inc/filelists.php');
                    $ep->setSubject(str_replace('</head>', $file_list_templates . '</head>', $ep->getSubject()));
                }
            });
        } elseif (rex_be_controller::getCurrentPage() == $addon->getName() . '/bulk_rework') {
            rex_view::addCssFile($this->getAssetsUrl('uploader.css'));
            rex_view::addJsFile($this->getAssetsUrl('uploader_bulk_rework.js'));

            rex_extension::register('REX_LIST_GET', function (rex_extension_point $ep) use ($addon) {
                /** @var BulkReworkList $list  */
                $list = $ep->getSubject();
                $sql = $list->getSql();

                // get query string
                $reflection = new ReflectionClass($sql);
                $sqlProperty = $reflection->getProperty('query');
                $query = $sqlProperty->getValue($sql);

                if (is_string($query) && preg_match('@ORDER BY `filesize`@', $query)) {
                    $query = preg_replace('@ORDER BY `filesize`@', 'ORDER BY CAST(`filesize` as SIGNED INTEGER)', $query);
                    $list->setCustomQuery($query);
                }
            });
        }

        // add checkbox to allow rescaling on image update/re-upload
        $file_id = rex_request('file_id', 'int', 0);
        if (
            rex_be_controller::getCurrentPage() == 'mediapool/media' &&
            $file_id > 0 &&
            rex_addon::get('media_manager')->isAvailable()
        ) {
            $maxWidth = (int)$addon->getConfig('image-max-width', 0);
            $maxHeight = (int)$addon->getConfig('image-max-height', 0);

            rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep) use ($file_id, $addon, $maxWidth, $maxHeight) {
                $media = rex_sql::factory()->getArray('SELECT filename FROM ' . rex::getTable('media') . ' WHERE id = :id', ['id' => $file_id]);

                if (count($media)) {
                    $media = rex_media::get($media[0]['filename']);

                    if (
                        $media != null && $media->isImage() &&
                        !($maxWidth == 0 && $maxHeight == 0)
                    ) {
                        $suchmuster = '<input type="file" name="file_new" />';
                        $resize = $addon->getConfig('image-resize-checked') == 'true' ? 'checked' : '';

                        $ersetzen = $suchmuster . '<label style="font-weight: normal;"><input type="checkbox" ' . $resize . ' id="resize-image" name="resize-image"> ' . $addon->i18n('mediapool_details_resize_image') . '</label>' .
                            '<div class="alert alert-info" hidden data-new-size-wrap>' .
                            rex_i18n::msg('uploader_resizer_standalone_calculated_size') . ': <span data-new-size></span><br />' .
                            rex_i18n::msg('uploader_resizer_standalone_original_size') . ': <span data-old-size></span>' .
                            '</div>' .
                            '<div class="alert alert-danger" hidden data-new-size-error>' . rex_i18n::msg('uploader_resizer_standalone_error') . '</div>';
                        $ep->setSubject(str_replace($suchmuster, $ersetzen, $ep->getSubject()));
                    }
                }
            });

            // resize image on update/re-upload
            rex_extension::register('MEDIA_UPDATED', function (rex_extension_point $ep) use ($addon, $maxWidth, $maxHeight) {
                $filename = $ep->getParam('filename', '');

                if (isset($_FILES['file_new']) && rex_request('resize-image', 'string', 'off') === 'on') {
                    BulkRework::reworkFile($filename, $maxWidth, $maxHeight);
                }
            });
        }
    }
}, rex_extension::LATE);
