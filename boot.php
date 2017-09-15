<?php
$addon = rex_addon::get('jquery_file_upload');
$addon->setProperty('uploadfolder', rex_path::media());

rex_extension::register('PACKAGES_INCLUDED', function ()
{
    if (rex::isBackend() && rex::getUser())
    {
        if (rex::isDebugMode() && rex_request_method() == 'get')
        {
            $compiler = new rex_scss_compiler();
            $compiler->setRootDir($this->getPath());
            $compiler->setScssFile($this->getPath('scss/jquery_file_upload.scss'));
            $compiler->setCssFile($this->getPath('assets/jquery_file_upload.css'));
            $compiler->compile();
            rex_file::copy($this->getPath('assets/jquery_file_upload.css'), $this->getAssetsPath('jquery_file_upload.css'));
            rex_file::copy($this->getPath('assets/jquery_file_upload.js'), $this->getAssetsPath('jquery_file_upload.js'));
        }
        $include_assets = 0;
        if (rex_get('page', 'string') == 'mediapool/upload')
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
        elseif (rex_get('page', 'string') == 'jquery_file_upload/upload')
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
            rex_view::addCssFile($this->getAssetsUrl('jquery_file_upload.css'));
            rex_view::addJsFile($this->getAssetsUrl('jquery_file_upload.js'));

            rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep)
            {
                $buttonbar_template = include(rex_path::addon('jquery_file_upload') . 'inc/buttonbar.php');
                $ep->setSubject(str_replace('</body>', $buttonbar_template . '</body>', $ep->getSubject()));
                $vars = include(rex_path::addon('jquery_file_upload') . 'inc/vars.php');
                $file_list_templates = include(rex_path::addon('jquery_file_upload') . 'inc/filelists.php');
                $ep->setSubject(str_replace('</head>', $file_list_templates . $vars . '</head>', $ep->getSubject()));
            });
        }

    }
}, 'LATE');
