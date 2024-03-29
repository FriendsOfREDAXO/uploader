<?php
class uploader_metainfo_handler extends rex_metainfo_handler
{
    const PREFIX = 'med_';

    public function buildFilterCondition(array $params)
    {


        $catId = rex_session('media[rex_file_category]', 'int');

        $s = '';
        if ($catId != 0)
        {
            $OOCat = rex_media_category::get($catId);

            // Alle Metafelder des Pfades sind erlaubt
            foreach ($OOCat->getPathAsArray() as $pathElement)
            {
                if ($pathElement != '')
                {
                    $s .= ' OR `p`.`restrictions` LIKE "%|' . $pathElement . '|%"';
                }
            }
        }

        // Auch die Kategorie selbst kann Metafelder haben
        $s .= ' OR `p`.`restrictions` LIKE "%|' . $catId . '|%"';

        $restrictionsCondition = 'AND (`p`.`restrictions` = "" OR `p`.`restrictions` IS NULL ' . $s . ')';

        return $restrictionsCondition;
    }

    public function handleSave(array $params, rex_sql $sqlFields)
    {
        if (rex_request_method() != 'post' || !isset($params['id']))
        {
            return $params;
        }

        $media = rex_sql::factory();
        //  $media->setDebug();
        $media->setTable(rex::getTablePrefix() . 'media');
        $media->setWhere('id=:mediaid', ['mediaid' => $params['id']]);

        //Bugfix: daten werden in Kombination mit gespeicherten Daten in MEDIA_ADDED und MEDIA_UPDATED verloren,
        //weil die aktuellen POST-Vars hineingeschrieben werden, auch wenn sie leer sind.
        //Benötigt einen Fix in metainfo, weil es den letzten Parameter nicht gibt (https://github.com/redaxo/redaxo/pull/5852)
//        parent::fetchRequestValues($params, $media, $sqlFields, false);
        parent::fetchRequestValues($params, $media, $sqlFields);

        // do the save only when metafields are defined
        if ($media->hasValues())
        {
            $media->update();
        }

        return $params;
    }

    public function renderFormItem($field, $tag, $tag_attr, $id, $label, $labelIt, $typeLabel)
    {
    }

    public function extendForm(rex_extension_point $ep)
    {
    }

    public function save ($params) {
        $sql = rex_sql::factory();
        $qry = 'SELECT id FROM ' . rex::getTablePrefix() . 'media WHERE filename="' . $params['filename'] . '"';
        $sql->setQuery($qry);
        if ($sql->getRows() == 1) {
            $params['id'] = $sql->getValue('id');
        }
        $filterCondition = $this->buildFilterCondition($params);
        $sqlFields = $this->getSqlFields('med_', $filterCondition);
        $params = $this->handleSave($params, $sqlFields);
    }
}
