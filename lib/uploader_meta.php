<?php
/**
 * Vereinfachte Klasse zum Speichern von Metadaten im Uploader-Addon
 */
class uploader_meta
{
    /**
     * Speichert die Metadaten eines Mediums
     *
     * @param array $params Parameter mit Metadaten
     * @return void
     */
    public static function save($params)
    {
        // Hier können wir prüfen, ob wir die nötigen Parameter haben
        if (!isset($params['filename'])) {
            return;
        }
        
        $handler = new uploader_metainfo_handler();
        $handler->save($params);
    }
    
    /**
     * Hilfsmethode, um alle Metafelder für ein Medium zu erhalten
     *
     * @param int $mediaId ID des Mediums
     * @return array Liste der Metafelder
     */
    public static function getMetaFields($mediaId)
    {
        $handler = new uploader_metainfo_handler();
        return $handler->getMetaFields($mediaId);
    }
    
    /**
     * Hilfsmethode, um Metadaten für alle Medien einer Kategorie zu aktualisieren
     *
     * @param int $categoryId Kategorie-ID
     * @param array $metaData Zu aktualisierende Metadaten (Key-Value-Paare)
     * @return int Anzahl der aktualisierten Medien
     */
    public static function updateCategoryMeta($categoryId, array $metaData)
    {
        $count = 0;
        $category = rex_media_category::get($categoryId);
        
        if ($category) {
            $mediaList = $category->getMedia();
            
            foreach ($mediaList as $media) {
                $params = [
                    'id' => $media->getId(),
                    'filename' => $media->getFileName(),
                ];
                
                // Metadaten hinzufügen
                $params = array_merge($params, $metaData);
                
                // Speichern
                self::save($params);
                $count++;
            }
        }
        
        return $count;
    }
}
