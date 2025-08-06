<?php

/** @var rex_addon $this */

use FriendsOfRedaxo\Uploader\BulkRework;
use FriendsOfRedaxo\Uploader\BulkReworkList;

echo rex_view::title($this->i18n('title'));

$listDebug = false;
$addon = rex_addon::get('uploader');
$maxWidth = (int) $addon->getConfig('image-max-width', 0);
$maxHeight = (int) $addon->getConfig('image-max-height', 0);

// Zeige Informationen über verfügbare Bildverarbeitungsbibliotheken
$imageLibInfo = [];
if (BulkRework::hasGD()) {
    $imageLibInfo[] = '<span class="text-success"><i class="rex-icon fa-check"></i> GD verfügbar</span>';
}
if (BulkRework::hasImageMagick()) {
    if (class_exists('Imagick')) {
        $imageLibInfo[] = '<span class="text-success"><i class="rex-icon fa-check"></i> ImageMagick (PHP Extension) verfügbar</span>';
    } else {
        $imageLibInfo[] = '<span class="text-success"><i class="rex-icon fa-check"></i> ImageMagick (Binary) verfügbar</span>';
    }
}

if (!empty($imageLibInfo)) {
    echo rex_view::info('Verfügbare Bildverarbeitungsbibliotheken: ' . implode(', ', $imageLibInfo));
}

// Alte synchrone Verarbeitung entfernt - nur noch async
// if(count($filesToRework = rex_request('rework-file', 'array', [])) > 0 && ...)

// Bereinige alte Batch-Dateien
BulkRework::cleanupOldBatches();

// apply searches to query
$search = [];

$searchFilename = rex_request('rework-files-search-filename', 'string');
$searchMediaCategories = rex_request('rework-files-search-media-category', 'string');
$searchMinFilesize = rex_request('rework-files-search-min-filesize', 'int');
$searchMinWidth = rex_request('rework-files-search-min-width', 'int');
$searchMinHeight = rex_request('rework-files-search-min-height', 'int');

if ($searchFilename)
{
    $search[] = 'filename LIKE ' . rex_sql::factory()->escape('%' . $searchFilename . '%');
}

if ($searchMediaCategories)
{
    $categories = array_map('intval', explode(',', $searchMediaCategories));
    $categories = array_values($categories);

    if (!empty($categories))
    {
        $search[] = 'category_id IN (' . implode(',', $categories) . ')';
    }
}

if ($searchMinFilesize > 0)
{
    $search[] = 'CAST(`filesize` as SIGNED INTEGER) >= ' . ($searchMinFilesize * 1024);
}

if ($searchMinWidth > 0)
{
    $search[] = 'width >= ' . $searchMinWidth;
}

if ($searchMinHeight > 0)
{
    $search[] = 'height >= ' . $searchMinHeight;
}

// build query
$sql = '
    SELECT
        *
    FROM
        ' . rex::getTable('media') . '
    WHERE
        filetype LIKE "image/%"
        ' . ($maxWidth > 0 ? ' AND (width > ' . $maxWidth : '') . '
        ' . ($maxHeight > 0 ? ($maxWidth > 0 ? ' OR ' : ' AND (') . ' height > ' . $maxHeight : '') . ')
        ' . (!empty($search) ? ' AND ' . implode(' AND ', $search) : '') . '
';

$list = BulkReworkList::factory($sql, 100, 'uploader-bulk-rework', $listDebug, 1, ['id' => 'desc']);
$list->addParam('page', rex_be_controller::getCurrentPage());
$list->addTableAttribute('class', 'table table-striped table-hover uploader-bulk-rework-table');
$list->addTableAttribute('id', 'uploader-bulk-rework-table');
$list->setNoRowsMessage($addon->i18n('list_no_rows'));

$tdIcon = '<i class="fa fa-coffee"></i>';
$thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '"' . rex::getAccesskey(rex_i18n::msg('add'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>';
$list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
$list->setColumnParams($thIcon, ['func' => 'edit', 'pid' => '###pid###']);

$allowedFields = ['id', 'filename', 'category_id', 'filesize', 'width', 'height', 'createdate', 'createuser'];
$existingFields = $list->getColumnNames();

foreach($existingFields as $field)  {
    if (!in_array($field, $allowedFields))
    {
        $list->removeColumn($field);
    }
}

$list->setColumnLabel('id', rex_i18n::msg('id'));
$list->setColumnSortable('id');
$list->setColumnFormat('id', 'custom', static function ($params) use ($list) {
    // rewrite filesize to kbyte with dot as thousands separator and 3 comma as decimal separator
    return '<label for="rework-file-' . $list->getValue('id') . '">' . $params['subject'] . '</label>';
});

$list->setColumnLabel('filename', rex_i18n::msg('pool_filename'));
$list->setColumnSortable('filename');
$list->setColumnFormat('filename', 'custom', static function ($params) use ($list) {
    // rewrite filesize to kbyte with dot as thousands separator and 3 comma as decimal separator
    return (trim($list->getValue('title')) != '' ?
            '<i class="column-title-tooltip-hint rex-icon rex-icon-info" data-html="true" data-toggle="tooltip" data-placement="top" title="' .
                '<b>' . rex_i18n::msg('pool_file_title') . '</b>: ' . $list->getValue('title') .
            '"></i>' :
            ''
           ) . $params['subject'];
});

$list->setColumnLabel('category_id', '<nobr>' . rex_i18n::msg('pool_file_category') . '</nobr>');
$list->setColumnSortable('category_id');

$list->setColumnLabel('filesize', '<nobr>' . $addon->i18n('bulk_rework_table_column_filesize') . '</nobr>');
$list->setColumnSortable('filesize');
$list->setColumnFormat('filesize', 'custom', static function ($params) {
    // rewrite filesize to kbyte with dot as thousands separator and 3 comma as decimal separator
    return preg_replace(
        '@(,\d+)$@',
        '<span class="column-filesize-bytes">$1</span>',
        number_format(
            $params['subject'] / 1024,
            3,
            ',',
            '.'
        )
    );
});

$list->setColumnLabel('width', '<nobr>' . $addon->i18n('bulk_rework_table_column_width') . '</nobr>');
$list->setColumnSortable('width');
$list->setColumnFormat('width', 'custom', static function ($params) use ($maxWidth) {
    if ($maxWidth > 0 && (int)$params['subject'] > $maxWidth)
    {
        $factor = (2 - ($params['subject'] - $maxWidth) / $maxWidth);

        if($factor < 0.3)
        {
            $factor = 0.3;
        }
        elseif($factor > 1.7)
        {
            $factor = 1.7;
        }

        $percentPlus = round(($params['subject'] / $maxWidth - 1) * 100);

        return $params['subject'] .
            '<span class="dimension-too-large" style="background-color: ' .
                BulkRework::lightenColor('FF0000', $factor) .
            ';" data-toggle="tooltip" data-placement="top" title="+ ' . $percentPlus . '%"></span>';
    }

    return $params['subject'];
});

$list->setColumnLabel('height', '<nobr>' . $addon->i18n('bulk_rework_table_column_height') . '</nobr>');
$list->setColumnSortable('height');
$list->setColumnFormat('height', 'custom', static function ($params) use ($maxHeight) {
    if ($maxHeight > 0 && (int)$params['subject'] > $maxHeight)
    {
        $factor = (2 - ($params['subject'] - $maxHeight) / $maxHeight);

        if($factor < 0.3)
        {
            $factor = 0.3;
        }

        $percentPlus = round(($params['subject'] / $maxHeight - 1) * 100);

        return $params['subject'] .
            '<span class="dimension-too-large" style="background-color: ' .
                BulkRework::lightenColor('FF0000', $factor) .
            ';" data-toggle="tooltip" data-placement="top" title="+ ' . $percentPlus . '%"></span>';
    }

    return $params['subject'];
});

$list->setColumnLabel('title', rex_i18n::msg('pool_file_title'));
$list->setColumnSortable('title');

$list->setColumnLabel('createdate', '<nobr>' . rex_i18n::msg('created_on') . '</nobr>');
$list->setColumnSortable('createdate');
$list->setColumnFormat('createdate', 'custom', static function ($params) {
    $date = new DateTime($params['subject']);
    return '<nobr>' . $date->format('d.m.Y H:i:s') . '</nobr>';
});

$list->setColumnLabel('createuser', '<nobr>' . rex_i18n::msg('created_by') . '</nobr>');
$list->setColumnSortable('createuser');

// checkbox column
$list->addColumn(
    'toggle-select-all',
    '<input type="checkbox" class="rex-table-checkbox" id="rework-file-###id###" name="rework-file[]" value="###id###" />',
    0
);
$list->setColumnLayout(
    'toggle-select-all',
    [
        '<th><input type="checkbox" class="rex-table-checkbox" name="rework-files-toggle" value="" data-toggle="tooltip" data-placement="top" title="' .
                $addon->i18n('bulk_rework_table_select_toggle') .
        '"/></nobr></th>',
        '<td class="column-select">###VALUE###</td>'
    ]
);
$list->setColumnFormat('toggle-select-all', 'custom', static function ($params) use ($list) {
    return str_replace('value="###id###"', 'value="' . $list->getValue('filename') . '"', $params['subject']);
});

$listContent = $list->get();

// buttons
$formElements = $n = [];
$n['field'] = $submitButton = '<button class="btn btn-save pull-right" type="submit" name="rework-files-submit" value="1">' .
                                    sprintf($addon->i18n('bulk_rework_submit'), '<span class="number">0</span>') .
                              '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('title',
    $addon->i18n('bulk_rework_table_title') .
           '<div class="small uploader-bulk-rework-current-settings">' . sprintf($addon->i18n('bulk_rework_current_settings'), $maxWidth, $maxHeight) . '</div>'.
           '<div class="small uploader-bulk-rework-hits">' . $list->getRows() . ' ' . $addon->i18n('bulk_rework_table_hits') . '</div>',
    false
);
$fragment->setVar('options', preg_replace('@(btn btn-save)@', '$1 btn-xs', $submitButton), false);
$fragment->setVar('content', $listContent, false);
$table = $fragment->parse('core/page/section.php');

// create search form
$searchFields = [];

$searchFieldsColumns = [
    'filename' => 'col-lg-2 col-sm-9',
    'category-id' => 'col-lg-2 col-sm-3',
    'filesize' => 'col-lg-2 col-sm-3',
    'width' => 'col-lg-2 col-sm-3',
    'height' => 'col-lg-2 col-sm-3',
    'submit' => 'col-lg-2 col-sm-3',
];

$searchFields['filename'] = '<div class="' . $searchFieldsColumns['filename'] . '"><div class="form-group">
    <label for="search-filename">' . rex_i18n::msg('pool_filename') . '</label>
    <input class="form-control" type="text" id="search-filename" name="rework-files-search-filename" value="' .
        rex_request('rework-files-search-filename', 'string', '') . '">
</div></div>';

$searchFields['category-id'] = '<div class="' . $searchFieldsColumns['category-id'] . '"><div class="form-group">
    <label for="search-media-category">' . $addon->i18n('bulk_rework_search_media_category') . '</label>
    <input class="form-control" type="text" id="search-media-category" name="rework-files-search-media-category" value="' .
        rex_request('rework-files-search-media-category', 'string', '') . '" placeholder="' .
        $addon->i18n('bulk_rework_search_media_category_placeholder', ', ') . '">
</div></div>';

$searchFields['filesize'] = '<div class="' . $searchFieldsColumns['filesize'] . '"><div class="form-group">
    <label for="search-min-filesize">min. ' . $addon->i18n('bulk_rework_table_column_filesize') . '</label>
    <input class="form-control" type="number" id="search-min-filesize" name="rework-files-search-min-filesize" value="' .
        rex_request('rework-files-search-min-filesize', 'int', 0) . '" min="0">
</div></div>';

$searchFields['width'] = '<div class="' . $searchFieldsColumns['width'] . '"><div class="form-group">
    <label for="search-min-width">min. ' . $addon->i18n('bulk_rework_table_column_width') . '</label>
    <input class="form-control" type="number" id="search-min-width" name="rework-files-search-min-width" value="' .
        rex_request('rework-files-search-min-width', 'int', 0) . '" min="0">
</div></div>';

$searchFields['height'] = '<div class="' . $searchFieldsColumns['height'] . '"><div class="form-group">
    <label for="search-min-height">min. ' . $addon->i18n('bulk_rework_table_column_height') . '</label>
    <input class="form-control" type="number" id="search-min-height" name="rework-files-search-min-height" value="' .
        rex_request('rework-files-search-min-height', 'int', 0) . '" min="0">
</div></div>';

$searchFields['submit'] = '<div class="' . $searchFieldsColumns['submit'] . '"><div class="form-group">
    <label style="display: block;">&nbsp;</label>
    <div style="white-space: nowrap;" class=" pull-right">
        <button class="btn btn-primary" type="submit" name="rework-files-search-submit" value="1" data-toggle="tooltip" data-placement="top" title="' . $addon->i18n('bulk_rework_search_submit', '') . '">
            <i class="rex-icon rex-icon-search"></i>
        </button> 
        <button class="btn btn-delete" style="margin: 0 5px;" type="submit" name="rework-files-search-reset" value="1" data-toggle="tooltip" data-placement="top" title="' . $addon->i18n('bulk_rework_search_reset', '') . '">
            <i class="rex-icon fa-arrow-rotate-left"></i>
        </button>
    </div>
</div></div>';

$searchFields = '<div class="row rework-files-search">' . implode('', $searchFields) . '</div>';

// bring it all together
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit uploader-bulk-rework-wrapper', false);;
$fragment->setVar('title', $addon->i18n('bulk_rework_title'));
$fragment->setVar('body',  $searchFields . '<hr />' . $table, false);
$fragment->setVar('buttons', $buttons, false);
$content =  $fragment->parse('core/page/section.php');

// build query var for url params `sort` and `sorttype` to attach to form action
$query = rex_request('query', 'array', []);
$query['sort'] = 'id';
$query['sorttype'] = 'desc';
$query['page'] = rex_request('page', 'int', 1);
$query['func'] = 'bulk_rework';

$sort = rex_request('sort', 'string', null);
$sorttype = rex_request('sorttype', 'string', null);
$listname = rex_request('list', 'string', null);

$queryParams = [];

if ($sort) {
    $queryParams['sort'] = $sort;
}
if ($sorttype) {
    $queryParams['sorttype'] = $sorttype;
}
if ($listname) {
    $queryParams['list'] = $listname;
}

$actionUrl = rex_url::currentBackendPage();
if (!empty($queryParams)) {
    $actionUrl .= '&' . http_build_query($queryParams);
}

echo '
    <form action="' . $actionUrl . '" method="post">
        ' . $content . '
    </form>';
