<?php
$addon = rex_addon::get('uploader');

if (rex::isBackend() && rex::getUser()) {
    // Berechtigungen für das AddOn definieren
    rex_perm::register('uploader[]');
    rex_perm::register('uploader[page]');

    // Seite ausblenden, wenn Benutzer keine Berechtigung hat
    if (!rex::getUser()->hasPerm('uploader[page]')) {
        $page = $addon->getProperty('page');
        $page['hidden'] = true;
        $addon->setProperty('page', $page);
    }
}    

// Nach dem Laden aller Packages ausführen
rex_extension::register('PACKAGES_INCLUDED', function() use ($addon) {
    if (rex::isBackend() && rex::getUser() && rex::getUser()->hasPerm('uploader[]')) {
        // Im Debug-Modus Assets neu kompilieren
        if (rex::isDebugMode() && rex_request_method() == 'get') {
            $compiler = new rex_scss_compiler();
            $compiler->setRootDir($addon->getPath());
            $compiler->setScssFile($addon->getPath('scss/uploader.scss'));
            $compiler->setCssFile($addon->getPath('assets/uploader.css'));
            $compiler->compile();
            rex_file::copy($addon->getPath('assets/uploader.css'), $addon->getAssetsPath('uploader.css'));
            rex_file::copy($addon->getPath('assets/uploader.js'), $addon->getAssetsPath('uploader.js'));
        }

        // Prüfen, ob wir uns auf einer Seite befinden, wo der Uploader integriert werden soll
        $include_assets = false;
        $context = '';

        // Auf Upload-Seite des Medienpools
        if (rex_get('page', 'string') == 'mediapool/upload') {
            $context = 'mediapool_upload';
            $include_assets = true;
        }
        // Auf Medien-Seite des Medienpools (optional aktivieren)
        /*
        elseif (rex_get('page', 'string') == 'mediapool/media') {
            $context = 'mediapool_media';
            $include_assets = true;
        }
        */
        // Auf der Upload-Seite des Uploader-AddOns
        elseif (rex_get('page', 'string') == 'uploader/upload') {
            $context = 'addon_upload';
            $include_assets = true;
        }

        // Assets nur einbinden, wenn wir auf einer relevanten Seite sind
        if ($include_assets) {
            // Kontext für spätere Verwendung speichern
            $addon->setProperty('context', $context);
            
            // Endpoint für Datei-Upload setzen
            $addon->setProperty('endpoint', rex_url::currentBackendPage());

            // jQuery File Upload-Abhängigkeiten einbinden
            rex_view::addJsFile($addon->getAssetsUrl('vendor/JavaScript-Templates/js/tmpl.min.js'));
            rex_view::addJsFile($addon->getAssetsUrl('vendor/JavaScript-Load-Image/js/load-image.all.min.js'));
            rex_view::addJsFile($addon->getAssetsUrl('vendor/JavaScript-Canvas-to-Blob/js/canvas-to-blob.min.js'));
            
            // jQuery File Upload-Hauptdateien
            rex_view::addJsFile($addon->getAssetsUrl('vendor/jquery-file-upload/js/jquery.iframe-transport.js'));
            rex_view::addJsFile($addon->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload.js'));
            rex_view::addJsFile($addon->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-process.js'));
            rex_view::addJsFile($addon->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-image.js'));
            rex_view::addJsFile($addon->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-validate.js'));
            rex_view::addJsFile($addon->getAssetsUrl('vendor/jquery-file-upload/js/jquery.fileupload-ui.js'));
            
            // Stylesheet-Dateien einbinden
            rex_view::addCssFile($addon->getAssetsUrl('vendor/jquery-file-upload/css/jquery.fileupload.css'));
            rex_view::addCssFile($addon->getAssetsUrl('vendor/jquery-file-upload/css/jquery.fileupload-ui.css'));
            rex_view::addCssFile($addon->getAssetsUrl('uploader.css'));
            
            // Eigene JavaScript-Datei einbinden
            rex_view::addJsFile($addon->getAssetsUrl('uploader.js'));

            // HTML-Output filtern und eigene Templates einfügen
            rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep) use ($addon) {
                // HTML der Upload-Buttonleiste einfügen
                $buttonbar_template = include(rex_path::addon('uploader') . 'inc/buttonbar.php');
                $subject = $ep->getSubject();
                $subject = str_replace('</body>', $buttonbar_template . '</body>', $subject);
                
                // JavaScript-Variablen und Dateilisten-Templates einfügen
                $vars = include(rex_path::addon('uploader') . 'inc/vars.php');
                $file_list_templates = include(rex_path::addon('uploader') . 'inc/filelists.php');
                $subject = str_replace('</head>', $file_list_templates . $vars . '</head>', $subject);
                
                $ep->setSubject($subject);
            });
        }
    }
}, rex_extension::LATE);
