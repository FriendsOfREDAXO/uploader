<?php
$addon = rex_addon::get('uploader');

if (rex::isBackend() && rex::getUser())
{
    rex_perm::register('uploader[]');
    rex_perm::register('uploader[page]');
    rex_perm::register('uploader[batch_rework]');

    if (!rex::getUser()->hasPerm('uploader[page]'))
    {
        $page = $this->getProperty('page');
        $page['hidden'] = 'true';
        $this->setProperty('page', $page);
    }
}    

rex_extension::register('PACKAGES_INCLUDED', function ()
{
    if (rex::isBackend() && rex::getUser() && rex::getUser()->hasPerm('uploader[]'))
    {
        if (rex::isDebugMode() && rex_request_method() == 'get')
        {
            $compiler = new rex_scss_compiler();
            $compiler->setRootDir($this->getPath());
            $compiler->setScssFile($this->getPath('scss/uploader.scss'));
            $compiler->setCssFile($this->getPath('assets/uploader.css'));
            $compiler->compile();
            rex_file::copy($this->getPath('assets/uploader.css'), $this->getAssetsPath('uploader.css'));
            rex_file::copy($this->getPath('assets/uploader.js'), $this->getAssetsPath('uploader.js'));
        }
        $include_assets = 0;
        if (rex_get('page', 'string') == 'mediapool/upload' )
        {
            $this->setProperty('context', 'mediapool_upload');
            $include_assets = 1;
        }
        /*
        elseif (rex_get('page', 'string') == 'mediapool/media')
        {
            $this->setProperty('context', 'mediapool_media');
            $include_assets = 1;
        }
        */
        elseif (rex_get('page', 'string') == 'uploader/upload')
        {
            $this->setProperty('context', 'addon_upload');
            $include_assets = 1;
        }

        if ($include_assets)
        {
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

            rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep)
            {
                $buttonbar_template = include(rex_path::addon('uploader') . 'inc/buttonbar.php');
                $ep->setSubject(str_replace('</body>', $buttonbar_template . '</body>', $ep->getSubject()));
                $vars = include(rex_path::addon('uploader') . 'inc/vars.php');
                $file_list_templates = include(rex_path::addon('uploader') . 'inc/filelists.php');
                $ep->setSubject(str_replace('</head>', $file_list_templates . $vars . '</head>', $ep->getSubject()));
            });
        }

        // add checkbox to allow rescaling on image update/re-upload
        if(
            rex_be_controller::getCurrentPage() == 'mediapool/media' &&
            rex_request('file_id', 'int', 0) > 0 &&
            rex_addon::get('media_manager')->isAvailable()
        )
        {
            $addon = rex_addon::get('uploader');
            $maxWidth = (int)$addon->getConfig('image-max-width');
            $maxHeight = (int)$addon->getConfig('image-max-height');

            rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) use ($addon, $maxWidth, $maxHeight)
            {
                $media = rex_sql::factory()->getArray('SELECT filename FROM ' . rex::getTable('media') . ' WHERE id = :id', ['id' => rex_request('file_id', 'int')]);

                if(count($media))
                {
                    $media = rex_media::get($media[0]['filename']);

                    if(
                        $media != null && $media->isImage() &&
                        !($maxWidth == 0 && $maxHeight == 0)
                    )
                    {
                        $suchmuster = '<input type="file" name="file_new" />';
                        $resize = $addon->getConfig('image-resize-checked') == 'true' ? 'checked' : '';

                        $ersetzen = $suchmuster . '<label style="font-weight: normal;"><input type="checkbox" '.$resize.' id="resize-image" name="resize-image"> ' . $addon->i18n('mediapool_details_resize_image') . '</label>';
                        $ep->setSubject(str_replace($suchmuster, $ersetzen, $ep->getSubject()));
                    }
                }
            });

            // resize image on update/re-upload
            rex_extension::register('MEDIA_UPDATED', function(rex_extension_point $ep) use ($addon, $maxWidth, $maxHeight)
            {
                $filename = $ep->getParam('filename', '');
                $imageSizes = getimagesize(rex_path::media($filename));
                $media = rex_media::get($filename);

                if(
                    isset($_FILES['file_new']) &&
                    rex_request('resize-image', 'string', 'off') === 'on' &&
                    $media != null &&
                    $media->isImage() &&
                    is_array($imageSizes) &&
                    $imageSizes[0] > 0 &&
                    $imageSizes[1] > 0 &&
                    !($maxWidth == 0 && $maxHeight == 0) &&
                    (($maxWidth == 0 || $imageSizes[0] > $maxWidth) || ($maxHeight == 0 || $imageSizes[1] > $maxHeight))
                )
                {
                    $fileSize = filesize(rex_path::media($filename));
                    $cachePath = rex_path::addonCache('media_manager');

                    $rexmedia = new rex_managed_media(rex_path::media($filename));
                    $manager = new rex_media_manager($rexmedia);
                    $manager->setCachePath($cachePath);

                    $effect = new rex_effect_resize();
                    $effect->setMedia($rexmedia);
                    $effect->setParams([
                        'allow_enlarge' => 'not_enlarge',
                        'style' => 'maximum',
                        'width' => $maxWidth,
                        'height' => $maxHeight,
                    ]);
                    $effect->execute();
                    $rescaledFilesize = rex_string::size($rexmedia->getSource());

                    // replace file in media folder
                    rex_file::put(rex_path::media($filename), $rexmedia->getSource());

                    // update filesize and dimensions in database
                    $saveObject = rex_sql::factory();
                    $saveObject->setTable(rex::getTablePrefix() . 'media');
                    $saveObject->setWhere(['filename' => $filename]);
                    $saveObject->setValue('filesize', $rescaledFilesize);
                    $saveObject->setValue('width', $rexmedia->getWidth());
                    $saveObject->setValue('height', $rexmedia->getHeight());
                    $saveObject->update();

                    $ep->setParam('msg', $ep->getParam('msg') . '<br />Die Bilddatei wurde erfolgreich verkleinert. [AddOn: ' . $addon->getName() . ']');
                    rex_media_cache::delete($filename);
                }
            });
        }
    }

}, rex_extension::LATE);

