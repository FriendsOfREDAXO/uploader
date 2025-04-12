<?php
echo rex_view::title('Uploader');
$addon = rex_addon::get('uploader');

$rex_file_category = rex_request('rex_file_category', 'int', -1);
$PERMALL = rex::getUser()->getComplexPerm('media')->hasCategoryPerm(0);

if (!$PERMALL && !rex::getUser()->getComplexPerm('media')->hasCategoryPerm($rex_file_category)) {
    $rex_file_category = 0;
}

$cats_sel = new rex_media_category_select();
$cats_sel->setStyle('class="form-control"');
$cats_sel->setSize(1);
$cats_sel->setName('rex_file_category');
$cats_sel->setId('rex-mediapool-category');
$cats_sel->addOption(rex_i18n::msg('pool_kats_no'), '0');
$cats_sel->setSelected($rex_file_category);
?>

<section class="rex-page-section">
    <div class="panel panel-edit">
        <div class="panel-body">
            <form action="<?= $addon->getProperty('endpoint') ?>" method="post" enctype="multipart/form-data">
                <fieldset>
                    <dl class="rex-form-group form-group">
                        <dt>
                            <label for="rex-mediapool-title"><?= rex_i18n::msg('pool_file_title') ?></label>
                        </dt>
                        <dd>
                            <input class="form-control" type="text" name="ftitle" id="rex-mediapool-title">
                        </dd>
                    </dl>
                    <dl class="rex-form-group form-group">
                        <dt>
                            <label for="rex-mediapool-category"><?= rex_i18n::msg('pool_file_category') ?></label>
                        </dt>
                        <dd>
                            <?= $cats_sel->get() ?>
                        </dd>
                    </dl>
                </fieldset>

                <fieldset>
                    <dl class="rex-form-group form-group">
                        <dt></dt>
                        <dd>
                            <div id="uploader" 
                                class="dropzone" 
                                data-endpoint="<?= $addon->getProperty('endpoint') ?>"
                                data-accepted-files="<?= implode(',', rex_mediapool_getMediaTypeWhitelist()) ?>"
                                data-max-filesize="<?= $addon->getConfig('image-max-filesize', 10) ?>"
                                data-image-max-width="<?= $addon->getConfig('image-max-width', 4000) ?>"
                                data-image-max-height="<?= $addon->getConfig('image-max-height', 4000) ?>"
                                data-dict-default-message="<?= $addon->i18n('buttonbar_dropzone') ?>">
                            </div>
                        </dd>
                    </dl>
                </fieldset>
            </form>
        </div>
    </div>
</section>
