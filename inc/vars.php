<?php
$allowed_filetypes = implode('|', rex_addon::get('mediapool')->getProperty('allowed_doctypes'));
return '
<script>
var uploader_options = {
    messages: {
        maxNumberOfFiles: "'.rex_i18n::msg('uploader_errors_max_number_of_files').'",
        acceptFileTypes: "'.rex_i18n::msg('uploader_errors_accept_file_types').'",
        maxFileSize: "'.rex_i18n::msg('uploader_errors_max_file_size').'",
        minFileSize: "'.rex_i18n::msg('uploader_errors_min_file_size').'"
    },
    context: "'.$this->getProperty('context').'",
    endpoint: "'.$this->getProperty('endpoint').'",
    acceptFileTypes: /\.('.$allowed_filetypes.')$/i
};
</script>
';