<?php
$options = [
    'upload_dir' => rex_path::media(),
    'upload_url' => rex_url::media(),
    'accept_file_types' => rex_mediapool_getMediaTypeWhitelist(),
    'max_file_size' => rex_addon::get('uploader')->getConfig('image-max-filesize', 10) * 1024 * 1024
];

$error_messages = [
    1 => rex_i18n::msg('uploader_errors_1'),
    2 => rex_i18n::msg('uploader_errors_2'),
    3 => rex_i18n::msg('uploader_errors_3'),
    4 => rex_i18n::msg('uploader_errors_4'),
    6 => rex_i18n::msg('uploader_errors_6'),
    7 => rex_i18n::msg('uploader_errors_7'),
    8 => rex_i18n::msg('uploader_errors_8'),
    'post_max_size' => rex_i18n::msg('uploader_errors_post_max_size'),
    'max_file_size' => rex_i18n::msg('uploader_errors_max_file_size'),
    'min_file_size' => rex_i18n::msg('uploader_errors_min_file_size'),
    'accept_file_types' => rex_i18n::msg('uploader_errors_accept_file_types'),
    'max_number_of_files' => rex_i18n::msg('uploader_errors_max_number_of_files')
];

// CSRF Protection
if (rex_request_method() === 'POST') {
    $csrf = rex_csrf_token::factory('mediapool');
    if (!$csrf->isValid()) {
        http_response_code(403);
        exit(json_encode(['error' => rex_i18n::msg('csrf_token_invalid')]));
    }
}

$uploader = new uploader_upload_handler($options, true, $error_messages);
