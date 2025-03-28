<?php
// Erlaubte Dateitypen ermitteln
$allowed_filetypes = '""';	

// Prüfen, ob Beschränkungen auf bestimmte Dateitypen vorliegen
$args = rex_request('args', 'array', []);
if (isset($args['types']) && trim($args['types'])) {
    // Die moderne rex_mediapool::getAllowedExtensions() Methode verwenden
    $allowedExtensions = rex_mediapool::getAllowedExtensions($args);
    if (!empty($allowedExtensions)) {
        $allowed_filetypes = "/(\.|\/)(" . implode('|', $allowedExtensions) . ")$/i";
    }
}

// Maximale Dateigröße vom AddOn-Setting oder Standard setzen
$maxFileSize = (int)$this->getConfig('image-max-filesize', 10); // Standard: 10MB
$maxWidth = (int)$this->getConfig('image-max-width', 4000); // Standard: 4000px 
$maxHeight = (int)$this->getConfig('image-max-height', 4000); // Standard: 4000px

return '
<script>
var uploader_options = {
    // Fehlermeldungen
    messages: {
        maxNumberOfFiles: "' . rex_i18n::msg('uploader_errors_max_number_of_files') . '",
        acceptFileTypes: "' . rex_i18n::msg('uploader_errors_accept_file_types') . '",
        maxFileSize: "' . rex_i18n::msg('uploader_errors_max_file_size') . '",
        minFileSize: "' . rex_i18n::msg('uploader_errors_min_file_size') . '"
    },
    // Kontext (mediapool_upload, mediapool_media, addon_upload)
    context: "' . $this->getProperty('context', '') . '",
    
    // API-Endpunkt für Datei-Uploads
    endpoint: "' . $this->getProperty('endpoint', '') . '",
    
    // Bildverarbeitungseinstellungen
    loadImageMaxFileSize: ' . ($maxFileSize * 1000000) . ', // in Bytes
    imageMaxWidth: ' . $maxWidth . ',
    imageMaxHeight: ' . $maxHeight . ',
    
    // Erlaubte Dateitypen (null = alle erlauben, die nicht blockiert sind)
    acceptFileTypes: ' . $allowed_filetypes . ',
    
    // CSRF-Token für sicheres Hochladen
    csrf_token: "' . rex_csrf_token::factory('mediapool')->getUrlParams() . '"
};
</script>
';
