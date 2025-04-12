<?php

/** @var rex_addon $this */

$addon = rex_addon::get('uploader');
$content = '';

if (rex_post('config-submit', 'boolean')) {
    $settings = rex_post('settings', [
        ['image-max-width', 'int'],
        ['image-max-height', 'int'],
        ['image-max-filesize', 'int'],
        ['image-resize-checked', 'bool'],
    ]);
    
    $addon->setConfig($settings);
    
    if (rex_config::has('uploader')) {
        $content .= rex_view::success($addon->i18n('settings_saved'));
    }
}

$form = new rex_form_container();

// Bild Einstellungen
$field = $form->addInputField('number', 'settings[image-max-width]', $addon->getConfig('image-max-width'));
$field->setLabel($addon->i18n('settings_image_max_width'));
$field->setAttribute('min', '0');
$field->setAttribute('step', '100');
$field->setNotice($addon->i18n('settings_image_max_width_notice'));

$field = $form->addInputField('number', 'settings[image-max-height]', $addon->getConfig('image-max-height'));
$field->setLabel($addon->i18n('settings_image_max_height'));
$field->setAttribute('min', '0');
$field->setAttribute('step', '100');
$field->setNotice($addon->i18n('settings_image_max_height_notice'));

$field = $form->addInputField('number', 'settings[image-max-filesize]', $addon->getConfig('image-max-filesize', 10));
$field->setLabel($addon->i18n('settings_image_max_filesize'));
$field->setAttribute('min', '1');
$field->setAttribute('max', '100');
$field->setNotice($addon->i18n('settings_image_max_filesize_notice'));

$field = $form->addCheckboxField('settings[image-resize-checked]', $addon->getConfig('image-resize-checked'));
$field->setLabel($addon->i18n('settings_image_resize_checked'));
$field->addOption($addon->i18n('settings_image_resize_checked_label'), '1');
$field->setNotice($addon->i18n('settings_image_resize_checked_notice'));

$content .= $form->get();

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('settings_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
