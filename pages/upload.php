<?php
echo rex_view::title('Uploader');
$addon = rex_addon::get('uploader');

$rex_file_category = rex_request('rex_file_category', 'int', -1);
$PERMALL = rex::getUser()->getComplexPerm('media')->hasCategoryPerm(0);
if (!$PERMALL && !rex::getUser()->getComplexPerm('media')->hasCategoryPerm($rex_file_category))
{
    $rex_file_category = 0;
}
$cats_sel = new rex_media_category_select();
$cats_sel->setStyle('class="form-control"');
$cats_sel->setSize(1);
$cats_sel->setName('rex_file_category');
$cats_sel->setId('rex-mediapool-category');
$cats_sel->addOption(rex_i18n::msg('pool_kats_no'), '0');
$cats_sel->setSelected($rex_file_category);

$formElements = [];

$e = [];
$e['label'] = '<label for="rex-mediapool-title">' . rex_i18n::msg('pool_file_title') . '</label>';
$e['field'] = '<input class="form-control" type="text" name="ftitle" value="" id="rex-mediapool-title" />';
$formElements[] = $e;

$e = [];
$e['label'] = '<label for="rex-mediapool-category">' . rex_i18n::msg('pool_file_category') . '</label>';
$e['field'] = $cats_sel->get();
$formElements[] = $e;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content = $fragment->parse('core/form/form.php');

// Das Formular bleibt im Panel-Body: das Uploader-JS haengt Dropzone und
// Buttonleiste per $form.find('fieldset:last').after() innerhalb des Formulars ein.
$body = '
    <form id="fileupload" action="' . rex_escape($addon->getProperty('endpoint')) . '" method="POST" enctype="multipart/form-data">
        <fieldset>
            ' . $content . '
        </fieldset>
    </form>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', rex_i18n::msg('pool_file_insert'));
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');
