<?php

declare(strict_types=1);

class uploader_upload_handler {
    protected array $options;
    protected array $error_messages;
    protected mixed $response = null;

    public function __construct(array $options = null, bool $initialize = true, array $error_messages = null) {
        $this->options = [
            'script_url' => $this->get_full_url().'/'.$this->basename($this->get_server_var('SCRIPT_NAME')),
            'upload_dir' => rex_path::media(),
            'upload_url' => rex_url::media(),
            'input_stream' => 'php://input',
            'user_dirs' => false,
            'mkdir_mode' => 0755,
            'param_name' => 'file',
            'delete_type' => 'DELETE',
            'max_file_size' => null,
            'min_file_size' => 1,
            'accept_file_types' => '/.+$/i',
            'max_number_of_files' => null,
            'discard_aborted_uploads' => true
        ];
        
        if ($options) {
            $this->options = array_replace_recursive($this->options, $options);
        }
        
        $this->error_messages = [
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk',
            8 => 'A PHP extension stopped the file upload',
            'post_max_size' => 'The uploaded file exceeds the post_max_size directive in php.ini',
            'max_file_size' => 'File is too big',
            'min_file_size' => 'File is too small',
            'accept_file_types' => 'Filetype not allowed',
            'max_number_of_files' => 'Maximum number of files exceeded'
        ];

        if ($error_messages) {
            $this->error_messages = array_replace($this->error_messages, $error_messages);
        }

        if ($initialize) {
            $this->initialize();
        }
    }

    protected function initialize(): void {
        // CSRF-Schutz für alle schreibenden Zugriffe
        if (in_array($this->get_server_var('REQUEST_METHOD'), ['POST', 'PUT', 'DELETE'])) {
            $csrf = rex_csrf_token::factory('mediapool');
            if (!$csrf->isValid()) {
                http_response_code(403);
                exit(json_encode(['error' => rex_i18n::msg('csrf_token_invalid')]));
            }
        }

        switch ($this->get_server_var('REQUEST_METHOD')) {
            case 'OPTIONS':
            case 'HEAD':
                $this->head();
                break;
            case 'GET':
                $this->get();
                break;
            case 'PATCH':
            case 'PUT':
            case 'POST':
                $this->post();
                break;
            case 'DELETE':
                $this->delete();
                break;
            default:
                $this->header('HTTP/1.1 405 Method Not Allowed');
        }
    }

    protected function get_full_url(): string {
        $https = !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'on') === 0;
        return ($https ? 'https://' : 'http://').
            (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($_SERVER['SERVER_NAME'].
            ($https && $_SERVER['SERVER_PORT'] === 443 ||
            $_SERVER['SERVER_PORT'] === 80 ? '' : ':'.$_SERVER['SERVER_PORT'])));
    }

    protected function get_upload_path(?string $file_name = null): string {
        return $this->options['upload_dir'] . ($file_name ?? '');
    }

    protected function handle_file_upload(string $uploaded_file, string $name, int $size, string $type, 
        int $error, ?int $index = null): object {
        
        $file = new stdClass();
        $file->name = $this->get_file_name($uploaded_file, $name, $size, $type, $error, $index);
        $file->size = $size;
        $file->type = $type;

        if ($this->validate($uploaded_file, $file, $error, $index)) {
            $upload_dir = $this->get_upload_path();
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, $this->options['mkdir_mode'], true);
            }
            
            $file_path = $this->get_upload_path($file->name);
            
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                move_uploaded_file($uploaded_file, $file_path);
            } else {
                file_put_contents($file_path, fopen('php://input', 'r'));
            }
            
            $file_size = $this->get_file_size($file_path);
            if ($file_size === $file->size) {
                $file->url = $this->get_download_url($file->name);
                
                // Integration mit REDAXO Media Manager
                if (rex_media::get($file->name)?->isImage()) {
                    $file->thumbnailUrl = rex_url::frontendController([
                        'rex_media_type' => 'rex_mediapool_preview',
                        'rex_media_file' => $file->name
                    ]);
                }
            } else {
                unlink($file_path);
                $file->error = 'Failed to upload file';
            }
        }

        return $file;
    }

    protected function get_file_name(string $file_path, string $name, int $size, string $type, 
        int $error, ?int $index): string {
        
        $name = strip_tags($name);
        $name = rex_string::normalize($name);
        
        // Prüfe ob Datei bereits existiert
        while (file_exists($this->get_upload_path($name))) {
            $matches = [];
            if (preg_match('/(.*?)(?:_(\d+))?\.(.*?)$/', $name, $matches)) {
                $basename = $matches[1];
                $extension = $matches[3];
                $counter = isset($matches[2]) ? intval($matches[2]) + 1 : 1;
                $name = sprintf('%s_%d.%s', $basename, $counter, $extension);
            } else {
                $name = substr_replace($name, '_1', strrpos($name, '.'), 0);
            }
        }
        
        return $name;
    }

    protected function validate($uploaded_file, $file, $error, $index): bool {
        if ($error) {
            $file->error = $this->error_messages[$error] ?? $error;
            return false;
        }

        if (!preg_match($this->options['accept_file_types'], $file->name)) {
            $file->error = $this->error_messages['accept_file_types'];
            return false;
        }

        if ($uploaded_file && is_uploaded_file($uploaded_file)) {
            $file_size = $this->get_file_size($uploaded_file);
        } else {
            $file_size = $file->size;
        }

        if ($this->options['max_file_size'] && (
                $file_size > $this->options['max_file_size'] ||
                $file->size > $this->options['max_file_size'])
            ) {
            $file->error = $this->error_messages['max_file_size'];
            return false;
        }

        if ($this->options['min_file_size'] &&
            $file_size < $this->options['min_file_size']) {
            $file->error = $this->error_messages['min_file_size'];
            return false;
        }

        return true;
    }

    protected function get_file_size(string $file_path, bool $clear_stat_cache = false): int {
        if ($clear_stat_cache) {
            clearstatcache(true, $file_path);
        }
        return filesize($file_path);
    }

    protected function get_download_url(string $file_name): string {
        return $this->options['upload_url'].rawurlencode($file_name);
    }

    protected function get_server_var(string $id): ?string {
        return $_SERVER[$id] ?? null;
    }

    protected function basename(string $filepath, string $suffix = ''): string {
        return basename($filepath, $suffix);
    }

    protected function head(): void {
        $this->header('Pragma: no-cache');
        $this->header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->header('Content-Type: application/json');
    }

    protected function get(): void {
        $file_name = $this->get_file_name_param();
        if ($file_name) {
            $response = ['file' => $this->get_file_object($file_name)];
        } else {
            $response = ['files' => $this->get_file_objects()];
        }
        $this->generate_response($response);
    }

    protected function post(): void {
        $upload = $_FILES[$this->options['param_name']] ?? null;
        $files = [];
        
        if ($upload && is_array($upload['tmp_name'])) {
            foreach ($upload['tmp_name'] as $index => $value) {
                $files[] = $this->handle_file_upload(
                    $upload['tmp_name'][$index],
                    $upload['name'][$index],
                    (int)$upload['size'][$index],
                    $upload['type'][$index],
                    $upload['error'][$index],
                    $index
                );
            }
        } else {
            $files[] = $this->handle_file_upload(
                $upload['tmp_name'] ?? null,
                $upload['name'] ?? null,
                (int)($upload['size'] ?? 0),
                $upload['type'] ?? null,
                $upload['error'] ?? null
            );
        }
        
        $this->generate_response(['files' => $files]);
    }

    protected function delete(): void {
        $file_name = $this->get_file_name_param();
        $file_path = $this->get_upload_path($file_name);
        
        $success = is_file($file_path) && unlink($file_path);
        $this->generate_response(['success' => $success]);
    }

    protected function get_file_name_param(): ?string {
        return basename(strval($_GET[$this->options['param_name']] ?? ''));
    }
    
    protected function get_file_object(string $file_name): ?object {
        $file_path = $this->get_upload_path($file_name);
        if (is_file($file_path)) {
            $file = new stdClass();
            $file->name = $file_name;
            $file->size = $this->get_file_size($file_path);
            $file->url = $this->get_download_url($file->name);
            return $file;
        }
        return null;
    }

    protected function get_file_objects(): array {
        $upload_dir = $this->get_upload_path();
        if (!is_dir($upload_dir)) {
            return [];
        }
        return array_values(array_filter(array_map(
            [$this, 'get_file_object'],
            scandir($upload_dir)
        )));
    }

    protected function header(string $str): void {
        header($str);
    }

    protected function generate_response(array $response): void {
        $this->response = $response;
        $this->head();
        echo json_encode($response);
    }

    public function get_response(): mixed {
        return $this->response;
    }
}
