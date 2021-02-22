<?php
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

rex_extension::register('PACKAGES_INCLUDED', function ()
{
    if (rex::isBackend() && rex::getUser() && rex::getUser()->hasPerm('uploader[]'))
    {
        if (false && rex::isDebugMode() && rex_request_method() == 'get')
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

    }
}, rex_extension::LATE);

