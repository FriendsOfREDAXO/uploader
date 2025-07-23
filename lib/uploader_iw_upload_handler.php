<?php

class uploader_iw_upload_handler extends uploader_upload_handler
{
    /**
     * Postvars cache
     * @var array
     */
    private $savedPostVars;

    public function generate_response($content, $print_response = true)
    {
        $this->response = $content;
        if ($print_response) {
            // iw patch redaxo thumbnails laden
            
            foreach ($content['files'] as $v) {
                // HTML-Tags aus Fehlermeldungen entfernen
                if (isset($v->error)) {
                    $v->error = strip_tags($v->error);
                }
                if (isset($v->upload_complete)) {
                    $media = rex_media::get($v->name);
                    if ($media && $media->isImage()) {
                        $v->thumbnailUrl = 'index.php?rex_media_type=rex_mediapool_preview&rex_media_file=' . $v->name;
                        if (rex_file::extension($v->name) == 'svg') {
                            $v->thumbnailUrl = '/media/' . $v->name;
                        }
                    } else {
                        $file_ext         = rex_file::extension($v->name);
                        $icon_class       = '';
                        $v->icon          = 1;
                        $v->iconclass     = $icon_class;
                        $v->iconextension = $file_ext;
                    }
                } else {
                    $file_ext         = rex_file::extension($v->name);
                    $icon_class       = ' rex-mime-error';
                    $v->icon          = 1;
                    $v->iconclass     = $icon_class;
                    $v->iconextension = $file_ext;
                }
            }
            $json     = json_encode($content);
            $redirect = stripslashes((string)$this->get_post_param('redirect'));
            if ($redirect && preg_match($this->options['redirect_allow_target'], $redirect)) {
                $this->header('Location: ' . sprintf($redirect, rawurlencode($json)));
                return;
            }
            $this->head();
            if ($this->get_server_var('HTTP_CONTENT_RANGE')) {
                $files = isset($content[$this->options['param_name']]) ?
                    $content[$this->options['param_name']] : null;
                if ($files && is_array($files) && is_object($files[0]) && $files[0]->size) {
                    $this->header('Range: 0-' . (
                            $this->fix_integer_overflow((int)$files[0]->size) - 1
                        ));
                }
            }
            $this->body($json);
        }
        
        return $content;
    }
    
    protected function upcount_name_callback($matches)
    {
        // Erhöhe vorhandenen Zähler oder setze auf 1
        $index = isset($matches[1]) ? ((int)$matches[1]) + 1 : 1;
        $ext   = isset($matches[2]) ? $matches[2] : '';
        // Neuer Zähler im Format _Zähler vor der Erweiterung
        return '_'. $index . $ext;
    }
    
    protected function upcount_name($name)
    {
        // Suche nach optionalem _Zähler vor der Dateiendung und erhöhe diesen
        return preg_replace_callback(
            '/(?:_([\d]+))?(\.[^.]+)$/',
            array($this, 'upcount_name_callback'),
            $name,
            1
        );
    }
    
    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error,
        $index = null, $content_range = null) {
        $file = new \stdClass();
        $file->name = $this->get_file_name($uploaded_file, $name, $size, $type, $error,
            $index, $content_range);
        $file->size = $this->fix_integer_overflow((int)$size);
        $file->type = $type;
        
        if ($this->validate($uploaded_file, $file, $error, $index, $content_range)) {
            $this->handle_form_data($file, $index);
            $upload_dir = $this->get_upload_path();
            
            // Verzeichnis erstellen mit rex_dir
            if (!rex_dir::create($upload_dir)) {
                $file->error = 'Failed to create upload directory';
                return $file;
            }
            
            $file_path = $this->get_upload_path($file->name);
            $append_file = $content_range && is_file($file_path) &&
                $file->size > $this->get_file_size($file_path);
            
            // Upload-Verzeichnis überprüfen
            if (!rex_dir::isWritable(dirname($file_path))) {
                $file->error = 'Upload directory is not writable';
                return $file;
            }
                
            // Datei hochladen
            $upload_success = false;
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                // multipart/formdata uploads (POST method uploads)
                if ($append_file) {
                    if (!rex_file::append($file_path, rex_file::get($uploaded_file))) {
                        $file->error = 'Failed to append file';
                        return $file;
                    }
                } else {
                    // Move the uploaded file directly
                    if (rex_file::move($uploaded_file, $file_path)) {
                        $upload_success = true;
                    } else {
                        $file->error = 'Failed to move uploaded file';
                        return $file;
                    }
                }
            } else {
                // Non-multipart uploads (PUT method support)
                if (rex_file::put($file_path, file_get_contents($this->options['input_stream']))) {
                    $upload_success = true;
                } else {
                    $file->error = 'Failed to create file from input stream';
                    return $file;
                }
            }
            
            $file_size = $this->get_file_size($file_path, $append_file);
            if ($file_size === $file->size) {
                // iw patch start
                $file->upload_complete = 1;
                $orig_filename = basename($file_path);
                // jfucounter-Platzhalter früh ersetzen
                $orig_filename = preg_replace_callback(
                    '/\s*\(jfucounter(\d+)jfucounter\)/',
                    function($m){ return '_'.$m[1]; },
                    $orig_filename
                );
                
                // Für Medienpool vorbereiten
                $catid = rex_post('rex_file_category', 'int', 0);
                // Server-seitige Kontrolle der Kategorie-ID: Existenz prüfen für >0
                if ($catid > 0 && !rex_media_category::get($catid)) {
                    throw new rex_api_exception('Ungültige Kategorie-ID');
                }
                // Berechtigung prüfen für alle Kategorien, auch ID 0
                if (!rex::getUser()->getComplexPerm('media')->hasCategoryPerm($catid)) {
                    throw new rex_api_exception('Ungültige Kategorie-ID');
                }
                $title = rex_post('ftitle', 'string', '');
                
                // Use filename as title if option is activated
                if (rex_post("filename-as-title", "int", "") === 1) {
                    $title = pathinfo($orig_filename, PATHINFO_FILENAME);
                }
                
                try {
                    // Vorbereiten der Datei für den Medienpool
                    $mediaData = [
                        'category_id' => $catid,
                        'title' => $title,
                        'file' => [
                            'name' => $orig_filename,
                            'path' => $file_path,
                            'type' => rex_file::mimeType($file_path)
                        ]
                    ];
                    
                    // Die Prüfung, ob eine Datei bereits existiert, überlassen wir komplett rex_media_service
                    // Nur wenn die Datei nicht die aktuelle ist, sollte sie inkrementiert werden
                    // Der Dateiname wird in der rex_media_service::addMedia() automatisch normalisiert
                    $do_subindexing = is_file(rex_path::media($orig_filename)) && $orig_filename !== basename($file_path);
                    
                    // Verwende die rex_media_service Klasse für den Upload
                    $result = rex_media_service::addMedia($mediaData, $do_subindexing);
                    $file->name = $result['filename'];
                    
                    //vorläufiger Bugfix wegen überschriebener Daten aus MEDIA_ADDED / MEDIA_UPDATED
                    //gilt solange, wie der PR 5852 nicht gmerged wurde (https://github.com/redaxo/redaxo/pull/5852)
                    $mediaFile = rex_media::get($result['filename']);
                    $mediaMetaSql = rex_sql::factory();
                    $mediaMetaResult = $mediaMetaSql->getArray('SELECT column_name AS column_name FROM information_schema.columns WHERE table_name = "rex_media" AND column_name LIKE "med_%"');
                    $metainfos = [];

                    if(!isset($this->savedPostVars)) {
                        $this->savedPostVars = $_POST;
                    }

                    if ($mediaMetaSql->getRows() > 0) {
                        foreach ($mediaMetaResult as $metaField) {
                            if (!isset($metaField['column_name'])) {
                                continue;
                            }

                            $metaName = $metaField['column_name'];
                            $value = $mediaFile->getValue($metaName); //Bereits erfasster Wert durch MEDIA_ADDED/MEDIA_UPDATED
                            if(isset($this->savedPostVars[$metaName])) {
                                $postValue = $this->savedPostVars[$metaName];
                                // Handle both string and array values
                                if (is_string($postValue) && mb_strlen($postValue) > 0) {
                                    //Uploader-Feature: Nutze angegebene Daten für alle Dateien
                                    $value = $postValue;
                                } elseif (is_array($postValue) && !empty($postValue)) {
                                    //Handle array values (e.g., from multi-select fields)
                                    $value = implode(',', array_filter($postValue));
                                }
                            }

                            $metainfos[$metaName] = $value;
                            $_POST[$metaName] = $value;
                        }
                    }

                    // merge metainfos with success array
                    $result = array_merge($result, $metainfos);
                    
                    // metainfos schreiben
                    uploader_meta::save($result);
                    rex_media_cache::delete($file->name);
                    
                } catch (Exception $e) {
                    $file->error = $e->getMessage();
                    // Bei Fehler die ursprüngliche Datei löschen
                    if (is_file($file_path)) {
                        rex_file::delete($file_path);
                    }
                    return $file;
                }
                // iw patch end
                
                $file->url = $this->get_download_url($file->name);
                if ($this->has_image_file_extension($file->name)) {
                    if ($content_range && !$this->validate_image_file($file_path, $file, $error, $index)) {
                        rex_file::delete($file_path);
                    } else {
                        $this->handle_image_file($file_path, $file);
                    }
                }
            } else {
                $file->size = $file_size;
                if (!$content_range && $this->options['discard_aborted_uploads']) {
                    rex_file::delete($file_path);
                    $file->error = $this->get_error_message('abort');
                }
            }
            $this->set_additional_file_properties($file);
        }
        return $file;
    }
    
    /**
     * Überschreiben der Methode, um rex_file::extension zu verwenden
     */
    protected function has_image_file_extension($file_path) {
        return !!preg_match('/\.(gif|jpe?g|png)$/i', rex_file::extension($file_path));
    }
    
    /**
     * Überschreiben der Methode, um rex_file für Dateioperationen zu verwenden
     */
    protected function get_file_size($file_path, $clear_stat_cache = false) {
        if ($clear_stat_cache) {
            clearstatcache(true, $file_path);
        }
        return $this->fix_integer_overflow(filesize($file_path));
    }
    
    /**
     * Überschreiben der Methode, um rex_path zu verwenden
     */
    protected function get_upload_path($file_name = null, $version = null) {
        $file_name = $file_name ? $file_name : '';
        if (empty($version)) {
            $version_path = '';
        } else {
            $version_dir = @$this->options['image_versions'][$version]['upload_dir'];
            if ($version_dir) {
                return $version_dir.$this->get_user_path().$file_name;
            }
            $version_path = $version.'/';
        }
        return $this->options['upload_dir'].$this->get_user_path()
            .$version_path.$file_name;
    }
}
