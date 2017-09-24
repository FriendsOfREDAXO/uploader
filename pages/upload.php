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
?>

<section class="rex-page-section">
    <div class="panel panel-edit">
        <div class="panel-body">
            <form id="fileupload" action="<?php echo $addon->getProperty('endpoint'); ?>" method="POST"
                  enctype="multipart/form-data">
                <fieldset>
                    <dl class="rex-form-group form-group">
                        <dt>
                            <label for="rex-mediapool-title">Titel</label>
                        </dt>
                        <dd>
                            <input class="form-control" type="text" name="ftitle" value="" id="rex-mediapool-title">
                        </dd>
                    </dl>
                    <dl class="rex-form-group form-group">
                        <dt>
                            <label for="rex-mediapool-category"><?php echo rex_i18n::msg('pool_file_category'); ?></label>
                        </dt>
                        <dd>
                            <?php echo $cats_sel->get(); ?>
                        </dd>
                    </dl>
                </fieldset>
            </form>
        </div>
    </div>
</section>
