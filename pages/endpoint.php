<?php
$allowed_filetypes = implode('|', rex_addon::get('mediapool')->getProperty('allowed_doctypes'));
$options = [
    'upload_dir' => rex_path::media(),
    'upload_url' => rex_url::media(),
    'accept_file_types' => '/\.('.$allowed_filetypes.')$/i',
    'image_versions' => [
        '' => [
            'auto_orient' => true
        ]
    ]
];
$error_messages = [
        1 => rex_i18n::msg('jquery_file_upload_errors_1'),
        2 => rex_i18n::msg('jquery_file_upload_errors_2'),
        3 => rex_i18n::msg('jquery_file_upload_errors_3'),
        4 => rex_i18n::msg('jquery_file_upload_errors_4'),
        6 => rex_i18n::msg('jquery_file_upload_errors_6'),
        7 => rex_i18n::msg('jquery_file_upload_errors_7'),
        8 => rex_i18n::msg('jquery_file_upload_errors_8'),
        'post_max_size' => rex_i18n::msg('jquery_file_upload_errors_post_max_size'),
        'max_file_size' => rex_i18n::msg('jquery_file_upload_errors_max_file_size'),
        'min_file_size' => rex_i18n::msg('jquery_file_upload_errors_min_file_size'),
        'accept_file_types' => rex_i18n::msg('jquery_file_upload_errors_accept_file_types'),
        'max_number_of_files' => rex_i18n::msg('jquery_file_upload_errors_max_number_of_files'),
        'max_width' => rex_i18n::msg('jquery_file_upload_errors_max_width'),
        'min_width' => rex_i18n::msg('jquery_file_upload_errors_min_width'),
        'max_height' => rex_i18n::msg('jquery_file_upload_errors_max_height'),
        'min_height' => rex_i18n::msg('jquery_file_upload_errors_min_height'),
        'abort' => rex_i18n::msg('jquery_file_upload_errors_abort'),
        'image_resize' => rex_i18n::msg('jquery_file_upload_errors_image_resize')
];
$uploader = new jquery_file_upload_iw_upload_handler($options, true, $error_messages);
