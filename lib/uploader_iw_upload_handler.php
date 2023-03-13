<?php

class uploader_iw_upload_handler extends uploader_upload_handler
{
   
    public function generate_response($content, $print_response = true)
    {
        $this->response = $content;
        if ($print_response) {
            // iw patch redaxo thumbnails laden
            
            foreach ($content['files'] as $v) {
                if (isset($v->upload_complete)) {
                    $media = rex_media::get($v->name);
                    if ($media->isImage()) {
                        $v->thumbnailUrl = 'index.php?rex_media_type=rex_mediapool_preview&rex_media_file=' . $v->name;
                        if (rex_file::extension($v->name) == 'svg') {
                            $v->thumbnailUrl = '/media/' . $v->name;
                        }
                    } else {
                        $file_ext         = substr(strrchr($v->name, '.'), 1);
                        $icon_class       = '';
                        $v->icon          = 1;
                        $v->iconclass     = $icon_class;
                        $v->iconextension = $file_ext;
                    }
                } else {
                    $file_ext         = substr(strrchr($v->name, '.'), 1);
                    $icon_class       = ' rex-mime-error';
                    $v->icon          = 1;
                    $v->iconclass     = $icon_class;
                    $v->iconextension = $file_ext;
                }
            }
            $json     = json_encode($content);
            $redirect = stripslashes($this->get_post_param('redirect'));
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
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, $this->options['mkdir_mode'], true);
            }
            $file_path = $this->get_upload_path($file->name);
            $append_file = $content_range && is_file($file_path) &&
                $file->size > $this->get_file_size($file_path);
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                // multipart/formdata uploads (POST method uploads)
                if ($append_file) {
                    file_put_contents(
                        $file_path,
                        fopen($uploaded_file, 'r'),
                        FILE_APPEND
                    );
                } else {
                    move_uploaded_file($uploaded_file, $file_path);
                }
            } else {
                // Non-multipart uploads (PUT method support)
                file_put_contents(
                    $file_path,
                    fopen($this->options['input_stream'], 'r'),
                    $append_file ? FILE_APPEND : 0
                );
            }
            $file_size = $this->get_file_size($file_path, $append_file);
            if ($file_size === $file->size) {
                // iw patch start
                $file->upload_complete = 1;
                $old_name              = basename($file_path);
                $path_parts            = pathinfo($file_path);
                $new_name              = $path_parts['filename'];
                // initial auf false, ansonsten wuerde der mediapool immer eins hochgezaehlen
                $do_subindexing = false;
                
                // dateiname endet mit " (jfucounterXjfucounter)" -> vom uploader hochgezaehlt
                preg_match('/(.+)( \(jfucounter\d+jfucounter\))/', $new_name, $matches);
                if ($matches) {
                    $new_name = $matches[1];
                }
                
                // dateiname genauso fertig machen wie im medienpoolupload/ -sync
                $new_name = rex_string::normalize($new_name, '_', '-.');
                if (isset($path_parts['extension'])) {
                    // ---- ext checken - alle scriptendungen rausfiltern
                    if (in_array($path_parts['extension'], rex_addon::get('mediapool')->getProperty('blocked_extensions'))) {
                        $new_name                .= $path_parts['extension'];
                        $path_parts['extension'] = 'txt';
                    }
                    
                    // ---- multiple extension check
                    foreach (rex_addon::get('mediapool')->getProperty('blocked_extensions') as $ext) {
                        $new_name = str_replace($ext . '.', $ext . '_.', $new_name);
                    }
                    $new_name = $new_name . '.' . $path_parts['extension'];
                }
                
                // es gibt schon eine datei mit dem neuen namen, mp muss hochzaehlen
                if ($new_name != $old_name && is_file(rex_path::media($new_name))) {
                    $do_subindexing = true;
                }
                
                // finalen namen holen
                $new_name   = rex_mediapool_filename($new_name, $do_subindexing);
                $file->name = $new_name;
                $file_path  = rex_path::media($new_name);
                
                // datei umbenennen und synchronisieren
                rename(rex_path::media($old_name), rex_path::media($new_name));
                $catid   = rex_post('rex_file_category');
                $title   = rex_post('ftitle', 'string', '');

                if(rex_post("filename-as-title", "int", "") === 1) {
                    $title = $path_parts['filename'];
                }

                $success = rex_mediapool_syncFile($file->name, $catid, $title);
                
                // metainfos schreiben
                uploader_meta::save($success);
                
                // iw patch end
                
                $file->url = $this->get_download_url($file->name);
                if ($this->has_image_file_extension($file->name)) {
                    if ($content_range && !$this->validate_image_file($file_path, $file, $error, $index)) {
                        unlink($file_path);
                    } else {
                        $this->handle_image_file($file_path, $file);
                    }
                }
            } else {
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
