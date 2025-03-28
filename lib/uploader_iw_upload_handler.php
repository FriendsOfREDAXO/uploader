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
            // Bild-Vorschau und Icon-Informationen hinzufügen
            foreach ($content['files'] as $v) {
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
        $index = isset($matches[1]) ? ((int)$matches[1]) + 1 : 1;
        $ext   = isset($matches[2]) ? $matches[2] : '';
        
        return ' (jfucounter' . $index . 'jfucounter)' . $ext;
    }
    
    protected function upcount_name($name)
    {
        return preg_replace_callback(
            '/(?:(?: \(jfucounter([\d]+)jfucounter\))?(\.[^.]+))?$/',
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
            
            // Stelle sicher, dass das Upload-Verzeichnis existiert
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, $this->options['mkdir_mode'], true);
            }
            
            $file_path = $this->get_upload_path($file->name);
            $append_file = $content_range && is_file($file_path) &&
                $file->size > $this->get_file_size($file_path);
                
            // Hochladen oder Daten anhängen bei Chunk-Uploads
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                if ($append_file) {
                    file_put_contents($file_path, fopen($uploaded_file, 'r'), FILE_APPEND);
                } else {
                    move_uploaded_file($uploaded_file, $file_path);
                }
            } else {
                file_put_contents(
                    $file_path,
                    fopen($this->options['input_stream'], 'r'),
                    $append_file ? FILE_APPEND : 0
                );
            }
            
            // Prüfen ob der Upload vollständig ist
            $file_size = $this->get_file_size($file_path, $append_file);
            if ($file_size === $file->size) {
                $file->upload_complete = 1;
                $old_name = basename($file_path);
                $path_parts = pathinfo($file_path);
                
                // Dateinamenverarbeitung - Entferne Counter-Informationen
                $new_name = $path_parts['filename'];
                preg_match('/(.+)( \(jfucounter\d+jfucounter\))/', $new_name, $matches);
                if ($matches) {
                    $new_name = $matches[1];
                }
                
                // Verwende die Medienpool-API für die Dateinamenverarbeitung
                $filename = $new_name;
                if (isset($path_parts['extension'])) {
                    $filename .= '.' . $path_parts['extension'];
                }
                
                // Medienpool bestimmt den finalen Dateinamen
                $new_name = rex_mediapool::filename($filename, false);
                
                // Wenn der Name anders ist, muss die Datei umbenannt werden
                if ($new_name != $old_name) {
                    rename(rex_path::media($old_name), rex_path::media($new_name));
                }
                
                $file->name = $new_name;
                
                // Kategorien und Titel aus dem Post bekommen
                $catid = rex_post('rex_file_category', 'int', 0);
                $title = rex_post('ftitle', 'string', '');

                // Filename als Titel verwenden wenn entsprechende Option aktiviert
                if(rex_post("filename-as-title", "int", 0) === 1) {
                    $title = $path_parts['filename'];
                }

                // Bereite die Daten für rex_media_service::addMedia vor
                $data = [
                    'title' => $title,
                    'category_id' => $catid,
                    'file' => [
                        'name' => $new_name,
                        'path' => rex_path::media($new_name)
                    ]
                ];

                try {
                    // Verwende die rex_media_service API statt der alten Funktionen
                    $success = rex_media_service::addMedia($data, false);
                    
                    // Speichere die POST-Variablen für Metadaten
                    if(!isset($this->savedPostVars)) {
                        $this->savedPostVars = $_POST;
                    }

                    // Vorläufiger Bugfix für Metadaten-Verlust bei MEDIA_ADDED/MEDIA_UPDATED
                    $mediaMetaSql = rex_sql::factory();
                    $mediaMetaResult = $mediaMetaSql->getArray('SELECT column_name FROM information_schema.columns WHERE table_name = "' . rex::getTable('media') . '" AND column_name LIKE "med_%"');
                    
                    if ($mediaMetaResult && count($mediaMetaResult) > 0) {
                        $mediaFile = rex_media::get($new_name);
                        $metainfos = [];
                        
                        foreach ($mediaMetaResult as $metaField) {
                            if (!isset($metaField['column_name'])) {
                                continue;
                            }

                            $metaName = $metaField['column_name'];
                            // Wert entweder vom Media-Objekt oder aus gespeicherten POST-Variablen
                            $value = $mediaFile->getValue($metaName);
                            if(isset($this->savedPostVars[$metaName]) && mb_strlen($this->savedPostVars[$metaName]) > 0) {
                                $value = $this->savedPostVars[$metaName];
                            }

                            $metainfos[$metaName] = $value;
                            $_POST[$metaName] = $value;
                        }

                        // Merge metainfos mit Success-Array und speichere
                        $success = array_merge($success, $metainfos);
                        uploader_meta::save($success);
                    }
                    
                } catch (rex_api_exception $e) {
                    $file->error = $e->getMessage();
                }
                
                // URL für die Datei setzen und Bild-Verarbeitung
                $file->url = $this->get_download_url($file->name);
                if ($this->has_image_file_extension($file->name)) {
                    if ($content_range && !$this->validate_image_file($file_path, $file, $error, $index)) {
                        unlink($file_path);
                    } else {
                        $this->handle_image_file($file_path, $file);
                    }
                }
            } else {
                // Unvollständiger Upload
                $file->size = $file_size;
                if (!$content_range && $this->options['discard_aborted_uploads']) {
                    unlink($file_path);
                    $file->error = $this->get_error_message('abort');
                }
            }
            $this->set_additional_file_properties($file);
        }
        return $file;
    }
}
