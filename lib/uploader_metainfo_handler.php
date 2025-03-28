<?php
/**
 * Handler für Metainformationen im Uploader-Addon
 * Nutzt die native REDAXO Metainfo-API
 */
class uploader_metainfo_handler extends rex_metainfo_handler
{
    const PREFIX = 'med_';

    /**
     * Baut die Filter-Bedingung für die Metafelder auf Basis der aktuellen Kategorie auf
     *
     * @param array $params
     * @return string
     */
    public function buildFilterCondition(array $params)
    {
        $catId = rex_session('media[rex_file_category]', 'int', 0);

        $s = '';
        if ($catId != 0)
        {
            $category = rex_media_category::get($catId);
            if ($category) {
                // Alle Metafelder des Pfades sind erlaubt
                foreach ($category->getPathAsArray() as $pathElement)
                {
                    if ($pathElement != '')
                    {
                        $s .= ' OR `p`.`restrictions` LIKE "%|' . $pathElement . '|%"';
                    }
                }
            }
        }

        // Auch die Kategorie selbst kann Metafelder haben
        $s .= ' OR `p`.`restrictions` LIKE "%|' . $catId . '|%"';

        $restrictionsCondition = 'AND (`p`.`restrictions` = "" OR `p`.`restrictions` IS NULL ' . $s . ')';

        return $restrictionsCondition;
    }

    /**
     * Speichert die Metainformationen für ein Medium
     * 
     * @param array $params Metadaten des Mediums
     * @param rex_sql|null $sqlFields Optional SQL-Objekt mit Metafeldern
     * @return array Die aktualisierten Parameter
     */
    public function handleSave(array $params, ?rex_sql $sqlFields = null)
    {
        if (rex_request_method() != 'post' || !isset($params['id']))
        {
            return $params;
        }

        // Anstatt direkt in die Datenbank zu schreiben, verwenden wir den nativen Mechanismus
        // über den rex_media_manager::updateMedia
        
        $media = rex_sql::factory();
        $media->setTable(rex::getTable('media'));
        $media->setWhere('id=:mediaid', ['mediaid' => $params['id']]);

        if (!$sqlFields) {
            $filterCondition = $this->buildFilterCondition($params);
            $sqlFields = $this->getSqlFields(self::PREFIX, $filterCondition);
        }

        // Werte aus dem Request abrufen
        parent::fetchRequestValues($params, $media, $sqlFields);

        // Nur speichern, wenn Metafelder definiert sind
        if ($media->hasValues())
        {
            $media->update();
        }

        return $params;
    }

    /**
     * Vereinfachte Methode zum Speichern von Metadaten für eine Mediendatei
     *
     * @param array $params Parameter mit Metadaten
     */
    public function save(array $params) 
    {
        // Zuerst die ID des Mediums über den Dateinamen ermitteln
        if (!isset($params['id']) && isset($params['filename'])) {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT id FROM ' . rex::getTable('media') . ' WHERE filename=:filename', 
                ['filename' => $params['filename']]);
            
            if ($sql->getRows() == 1) {
                $params['id'] = $sql->getValue('id');
            }
        }

        // Keine ID gefunden - keine Verarbeitung möglich
        if (!isset($params['id'])) {
            return;
        }

        // Filter-Bedingung erstellen und alle verfügbaren Metafelder abholen
        $filterCondition = $this->buildFilterCondition($params);
        $sqlFields = $this->getSqlFields(self::PREFIX, $filterCondition);
        
        // Metadaten speichern
        $this->handleSave($params, $sqlFields);
    }

    /**
     * Hilfsmethode, um alle verfügbaren Metafelder für eine Mediendatei abzurufen
     *
     * @param int $mediaId ID des Mediums
     * @return array Liste der Metafeldnamen
     */
    public function getMetaFields($mediaId)
    {
        $media = rex_media::get($mediaId);
        if (!$media) {
            return [];
        }

        $category_id = $media->getCategoryId();
        $params = ['id' => $mediaId, 'category_id' => $category_id];
        
        $filterCondition = $this->buildFilterCondition($params);
        $sqlFields = $this->getSqlFields(self::PREFIX, $filterCondition);
        
        $fields = [];
        if ($sqlFields) {
            $rows = $sqlFields->getRows();
            for ($i = 0; $i < $rows; $i++) {
                $fields[] = $sqlFields->getValue('name');
                $sqlFields->next();
            }
        }
        
        return $fields;
    }

    /**
     * Diese Methoden werden von der Basisklasse gefordert, werden aber hier nicht genutzt
     */
    public function renderFormItem($field, $tag, $tag_attr, $id, $label, $labelIt, $typeLabel) 
    {
        // Wird nicht benötigt
    }

    public function extendForm(rex_extension_point $ep)
    {
        // Wird nicht benötigt
    }
}
