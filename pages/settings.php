<?php

/** @var rex_addon $this */

echo rex_view::title($this->i18n('title'));

if (rex_post('config-submit', 'boolean')) {
    $this->setConfig(rex_post('settings', [
        ['image-max-width', 'int'],
        ['image-max-height', 'int'],
        ['image-resize-checked', 'bool'],
        ['filename-as-title-checked', 'bool']
    ]));

    echo rex_view::success($this->i18n('settings_saved'));
}

$content = '<fieldset>';
$content .= '<legend>' . $this->i18n('settings_image_section') . '</legend>';
$content .= '<p>' . $this->i18n('settings_image_intro') . '</p><br>';

$formElements = [];

// max width
$inputGroups = [];
$n = [];
$n['field'] = '<input class="form-control" id="uploader-image-max-width" type="text" name="settings[image-max-width]" value="' . $this->getConfig('image-max-width') . '">';
$n['right'] = '<div style="min-width: 2em;">px</div>';
$inputGroups[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['before'] = '<div style="max-width: 200px;">';
$n['after'] = '</div>';
$n['label'] = '<label for="uploader-image-max-width">' . $this->i18n('settings_image_max_width') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

// max height
$inputGroups = [];
$n = [];
$n['field'] = '<input class="form-control" id="uploader-image-max-height" type="text" name="settings[image-max-height]" value="' . $this->getConfig('image-max-height') . '">';
$n['right'] = '<div style="min-width: 2em;">px</div>';
$inputGroups[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['before'] = '<div style="max-width: 200px;">';
$n['after'] = '</div>';
$n['label'] = '<label for="uploader-image-max-height">' . $this->i18n('settings_image_max_height') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// image resize checked per default
$formElements = [];
$n = [];
$n['label'] = '<label for="image-resize-checked">' . $this->i18n('settings_image_resize_checked') . '</label>';
$n['field'] = '<input type="checkbox" id="image-resize-checked" name="settings[image-resize-checked]" value="1" ' . ($this->getConfig('image-resize-checked') ? ' checked="checked"' : '') . '>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

// use file name as "title" attribute (default: OFF)
$formElements = [];
$n = [];
$n['label'] = '<label for="filename-as-title-checked">' . $this->i18n('uploader_settings_filename_as_title_checked') . '</label>';
$n['field'] = '<input type="checkbox" id="filename-as-title-checked" name="settings[filename-as-title-checked]" value="1" ' . ($this->getConfig('filename-as-title-checked') ? ' checked="checked"' : '') . '>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

// buttons
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1">' . $this->i18n('settings_save') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

// content
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $this->i18n('settings'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        ' . $content . '
    </form>';
