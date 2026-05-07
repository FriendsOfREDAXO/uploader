<?php

use FriendsOfRedaxo\Uploader\ImageResizer;

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
            rex_view::addJsFile($this->getAssetsUrl('image_resizer_standalone.js') . '?v=' . rawurlencode((string) $addon->getVersion()));

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
                        $resizeLocked = (bool) $addon->getConfig('image-resize-locked', false);
                        $isChecked = ($addon->getConfig('image-resize-checked') == 'true' || $resizeLocked) ? 'checked' : '';
                        $isDisabled = $resizeLocked ? 'disabled' : '';
                        $hiddenResizeInput = $resizeLocked ? '<input type="hidden" name="resize-image" value="on">' : '';

                        $previewUrl = rex_media_manager::getUrl('rex_media_small', $media->getFileName());
                        $currentW = (int) $media->getWidth();
                        $currentH = (int) $media->getHeight();
                        $isOversized = ($maxWidth > 0 && $currentW > $maxWidth) || ($maxHeight > 0 && $currentH > $maxHeight);
                        $statusLabel = $isOversized
                            ? '<span class="label label-warning" data-current-image-status style="font-size:11px;">' . $addon->i18n('mediapool_details_oversized') . '</span>'
                            : '<span class="label label-success" data-current-image-status style="font-size:11px;">' . $addon->i18n('mediapool_details_within_limits') . '</span>';
                        $fileInputId = 'uploader-file-new-' . $file_id;

                        $ersetzen = '<div class="input-group" style="max-width:520px;">'
                            . '<span class="input-group-btn">'
                            .   '<label class="btn btn-default" for="' . $fileInputId . '" style="margin-bottom:0;">' . $addon->i18n('mediapool_details_choose_file') . '</label>'
                            . '</span>'
                            . '<input class="form-control" type="text" readonly data-uploader-file-name value="' . htmlspecialchars($media->getFileName(), ENT_QUOTES) . '">'
                            . '</div>'
                            . '<input type="file" id="' . $fileInputId . '" name="file_new" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">'
                            . $hiddenResizeInput
                            . '<div class="uploader-resize-wrap" style="margin-top:8px;">'
                            . '<div class="media" style="margin-bottom:8px;">'
                            .   '<a class="media-left" data-current-image-link href="' . rex_url::media($media->getFileName()) . '" target="_blank" style="display:block;margin-right:10px;">'
                            .     '<img data-current-image-preview src="' . $previewUrl . '" alt="" style="max-width:64px;max-height:64px;border:1px solid #ddd;padding:2px;background:#fff;display:block;">'
                            .   '</a>'
                            .   '<div class="media-body">'
                            .     '<small class="text-muted" data-current-image-size>' . $currentW . ' &times; ' . $currentH . ' px (max. ' . $maxWidth . ' &times; ' . $maxHeight . ')</small>'
                            .     ' &nbsp;' . $statusLabel
                            .   '</div>'
                            . '</div>'
                            . '<label style="font-weight:normal;margin-bottom:4px;">'
                            .   '<input type="checkbox" id="resize-image" name="resize-image" ' . $isChecked . ' ' . $isDisabled . '> '
                            .   $addon->i18n('mediapool_details_resize_image')
                            .   ($resizeLocked ? ' <i class="rex-icon fa-lock" style="color:#aaa;" title="' . $addon->i18n('mediapool_details_resize_locked') . '"></i>' : '')
                            . '</label>'
                            . '<div class="alert alert-info" style="padding:8px 12px;margin-top:6px;margin-bottom:0;" hidden data-new-size-wrap>'
                            .   '<i class="rex-icon fa-compress"></i> '
                            .   '<strong>' . rex_i18n::msg('uploader_resizer_standalone_calculated_size') . ':</strong> <span data-new-size></span>'
                            .   '<br><small class="text-muted">' . rex_i18n::msg('uploader_resizer_standalone_original_size') . ': <span data-old-size></span></small>'
                            . '</div>'
                            . '<div class="alert alert-warning" style="padding:8px 12px;margin-top:6px;margin-bottom:0;" hidden data-new-size-error>'
                            .   rex_i18n::msg('uploader_resizer_standalone_error')
                            . '</div>'
                            . '</div>';
                        $ep->setSubject(str_replace($suchmuster, $ersetzen, $ep->getSubject()));
                    }
                }
            });

            // resize image on update/re-upload
            rex_extension::register('MEDIA_UPDATED', function (rex_extension_point $ep) use ($addon, $maxWidth, $maxHeight) {
                $filename = $ep->getParam('filename', '');
                $resizeLocked = (bool) $addon->getConfig('image-resize-locked', false);

                if (isset($_FILES['file_new']) && ($resizeLocked || rex_request('resize-image', 'string', 'off') === 'on')) {
                    ImageResizer::resizeIfNeeded($filename, $maxWidth, $maxHeight);
                }
            });
        }
    }
}, rex_extension::LATE);
