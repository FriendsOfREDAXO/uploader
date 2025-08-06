<?php

namespace FriendsOfRedaxo\Uploader;

use rex;
use rex_addon;
use rex_effect_resize;
use rex_file;
use rex_managed_media;
use rex_media;
use rex_media_cache;
use rex_media_manager;
use rex_path;
use rex_sql;
use rex_string;
use rex_logger;
use Exception;

/**
 * Class BulkRework
 *
 * @category
 * @package uploader\lib
 * @author Peter Schulze | p.schulze[at]bitshifters.de
 * @created 05.06.2025
 */
class BulkRework
{
    // Unterstützte Bildformate
    const SUPPORTED_FORMATS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'
    ];
    
    // Problematische Formate die standardmäßig übersprungen werden
    const SKIPPED_FORMATS = [
        'tif', 'tiff', 'psd', 'svg'
    ];
    
    // Formate die nur mit ImageMagick konvertiert werden können (optional)
    const CONVERTIBLE_FORMATS = [
        'tif', 'tiff'
    ];
    
    // Batch processing status cache key
    const BATCH_CACHE_KEY = 'uploader_bulk_batch_';
    
    /**
     * Prüft ob ein Bildformat verarbeitet werden kann
     *
     * @param string $filename
     * @param bool $allowConversion Ob TIF-Konvertierung erlaubt ist
     * @return array ['canProcess' => bool, 'needsImageMagick' => bool, 'format' => string]
     */
    public static function canProcessImage(string $filename, bool $allowConversion = false): array
    {
        $extension = strtolower(rex_file::extension($filename));
        
        $result = [
            'canProcess' => false,
            'needsImageMagick' => false,
            'needsConversion' => false,
            'format' => $extension,
            'reason' => ''
        ];
        
        if (in_array($extension, self::SUPPORTED_FORMATS)) {
            $result['canProcess'] = true;
        } elseif (in_array($extension, self::SKIPPED_FORMATS)) {
            if ($allowConversion && in_array($extension, self::CONVERTIBLE_FORMATS) && self::hasImageMagick()) {
                $result['canProcess'] = true;
                $result['needsImageMagick'] = true;
                $result['needsConversion'] = true;
            } else {
                $result['reason'] = $allowConversion 
                    ? 'Format benötigt ImageMagick für Konvertierung' 
                    : 'Format wird standardmäßig übersprungen (TIF/TIFF)';
            }
        } else {
            $result['reason'] = 'Nicht unterstütztes Format';
        }
        
        return $result;
    }
    
    /**
     * Prüft ob ImageMagick verfügbar ist
     *
     * @return bool
     */
    public static function hasImageMagick(): bool
    {
        return class_exists('Imagick') || !empty(self::getConvertPath());
    }
    
    /**
     * Ermittelt den Pfad zum ImageMagick convert Binary
     *
     * @return string
     */
    public static function getConvertPath(): string
    {
        $path = '';

        if (function_exists('exec')) {
            $out = [];
            $cmd = 'command -v convert || which convert';
            exec($cmd, $out, $ret);

            if (0 === $ret && !empty($out[0])) {
                $path = (string) $out[0];
            }
        }
        return $path;
    }
    
    /**
     * Prüft ob GD verfügbar ist
     *
     * @return bool
     */
    public static function hasGD(): bool
    {
        return extension_loaded('gd');
    }
    
    /**
     * Startet einen Batch-Verarbeitungsvorgang
     *
     * @param array $filenames
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @param bool $allowTifConversion
     * @param int $parallelProcessing Anzahl parallel zu verarbeitender Dateien (1-5)
     * @return string Batch-ID
     */
    public static function startBatchProcessing(array $filenames, ?int $maxWidth = null, ?int $maxHeight = null, bool $allowTifConversion = false, int $parallelProcessing = 1): string
    {
        $batchId = uniqid('batch_', true);
        
        // Parallel Processing auf 1-5 begrenzen
        $parallelProcessing = max(1, min(5, $parallelProcessing));
        
        $batchData = [
            'id' => $batchId,
            'filenames' => $filenames,
            'maxWidth' => $maxWidth,
            'maxHeight' => $maxHeight,
            'allowTifConversion' => $allowTifConversion,
            'parallelProcessing' => $parallelProcessing,
            'total' => count($filenames),
            'processed' => 0,
            'successful' => 0,
            'errors' => [],
            'skipped' => [],
            'status' => 'running',
            'currentFiles' => [], // Array statt einzelne Datei
            'processingQueue' => [], // Queue für parallele Verarbeitung
            'startTime' => time()
        ];
        
        // Status in Cache speichern
        rex_file::put(
            rex_path::addonCache('uploader', self::BATCH_CACHE_KEY . $batchId . '.json'),
            json_encode($batchData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        return $batchId;
    }
    
    /**
     * Holt den Status eines Batch-Vorgangs
     *
     * @param string $batchId
     * @return array|null
     */
    public static function getBatchStatus(string $batchId): ?array
    {
        $cacheFile = rex_path::addonCache('uploader', self::BATCH_CACHE_KEY . $batchId . '.json');
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $content = rex_file::get($cacheFile);
        return json_decode($content, true);
    }
    
    /**
     * Aktualisiert den Status eines Batch-Vorgangs
     *
     * @param string $batchId
     * @param array $updates
     * @return bool
     */
    private static function updateBatchStatus(string $batchId, array $updates): bool
    {
        $status = self::getBatchStatus($batchId);
        if (!$status) {
            return false;
        }
        
        $status = array_merge($status, $updates);
        
        rex_file::put(
            rex_path::addonCache('uploader', self::BATCH_CACHE_KEY . $batchId . '.json'),
            json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        return true;
    }
    
    /**
     * Bricht einen Batch-Vorgang ab (Graceful Cancellation)
     *
     * @param string $batchId
     * @return bool
     */
    public static function cancelBatch(string $batchId): bool
    {
        $batchStatus = self::getBatchStatus($batchId);
        
        if (!$batchStatus) {
            return false;
        }
        
        // Setze Status auf "cancelling" - laufende Verarbeitungen werden beendet
        self::updateBatchStatus($batchId, [
            'status' => 'cancelling',
            'cancelTime' => time()
        ]);
        
        return true;
    }
    
    /**
     * Verarbeitet die nächsten Bilder in einem Batch (parallel)
     *
     * @param string $batchId
     * @return array Status-Update
     */
    public static function processNextBatchItem(string $batchId): array
    {
        $batchStatus = self::getBatchStatus($batchId);
        
        if (!$batchStatus) {
            return ['status' => 'error', 'message' => 'Batch nicht gefunden'];
        }
        
        // Prüfe auf Abbruch-Status
        if ($batchStatus['status'] === 'cancelling') {
            // Beim Abbrechen: Prüfe ob noch laufende Verarbeitungen existieren
            if (empty($batchStatus['currentFiles'])) {
                // Keine aktuelle Verarbeitung - kann sofort abbrechen
                self::updateBatchStatus($batchId, [
                    'status' => 'cancelled',
                    'endTime' => time(),
                    'currentFiles' => []
                ]);
                return ['status' => 'cancelled', 'batch' => self::getBatchStatus($batchId)];
            } else {
                // Noch Dateien in Verarbeitung - warte bis diese fertig sind
                return ['status' => 'cancelling', 'batch' => $batchStatus, 'message' => 'Warte auf Beendigung laufender Verarbeitungen...'];
            }
        }
        
        // Normale Verarbeitung - sammle neue Dateien
        $processed = $batchStatus['processed'];
        $filenames = $batchStatus['filenames'];
        $parallelProcessing = $batchStatus['parallelProcessing'] ?? 1;
        
        if ($processed >= count($filenames)) {
            self::updateBatchStatus($batchId, [
                'status' => 'completed',
                'endTime' => time(),
                'currentFiles' => []
            ]);
            return ['status' => 'completed', 'batch' => self::getBatchStatus($batchId)];
        }
        
        // Bestimme wie viele Dateien parallel verarbeitet werden sollen
        $remainingFiles = count($filenames) - $processed;
        $filesToProcess = min($parallelProcessing, $remainingFiles);
        
        $currentFiles = [];
        
        // Sammle Dateien für parallele Verarbeitung
        for ($i = 0; $i < $filesToProcess; $i++) {
            $fileIndex = $processed + $i;
            if ($fileIndex < count($filenames)) {
                $currentFiles[] = $filenames[$fileIndex];
            }
        }
        
        // Status für aktuell verarbeitete Dateien aktualisieren
        self::updateBatchStatus($batchId, [
            'currentFiles' => $currentFiles
        ]);
        
        // Verarbeite alle Dateien parallel
        $successful = 0;
        $errors = $batchStatus['errors'];
        $skipped = $batchStatus['skipped'];
        
        foreach ($currentFiles as $currentFilename) {
            $result = self::reworkFileWithFallback(
                $currentFilename, 
                $batchStatus['maxWidth'], 
                $batchStatus['maxHeight'],
                $batchStatus['allowTifConversion'] ?? false
            );
            
            $results[] = $result;
            
            if ($result['success']) {
                $successful++;
            } elseif ($result['skipped']) {
                $skipped[$currentFilename] = $result['reason'];
            } else {
                $errors[$currentFilename] = $result['error'];
            }
        }
        
        // Batch-Status aktualisieren
        $processed = $batchStatus['processed'];
        $filesToProcess = count($currentFiles);
        
        $updates = [
            'processed' => $processed + $filesToProcess,
            'successful' => $batchStatus['successful'] + $successful,
            'errors' => $errors,
            'skipped' => $skipped,
            'currentFiles' => [] // Wichtig: Nach Verarbeitung leeren für Cancel-Logik
        ];
        
        self::updateBatchStatus($batchId, $updates);
        
        // Return status basiert auf aktuellem Batch-Status
        $finalBatchStatus = self::getBatchStatus($batchId);
        $returnStatus = $finalBatchStatus['status'] === 'cancelling' ? 'cancelling' : 'processing';
        
        return [
            'status' => $returnStatus,
            'batch' => $finalBatchStatus,
            'results' => $results,
            'processedCount' => $filesToProcess
        ];
    }
    
    /**
     * Verarbeitet eine Datei mit Fallback-System
     *
     * @param string $filename
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @param bool $allowConversion Ob TIF-Konvertierung erlaubt ist
     * @return array
     */
    public static function reworkFileWithFallback(string $filename, ?int $maxWidth = null, ?int $maxHeight = null, bool $allowConversion = false): array
    {
        try {
            // Prüfe ob Datei verarbeitet werden kann
            $canProcess = self::canProcessImage($filename, $allowConversion);
            
            if (!$canProcess['canProcess']) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'reason' => $canProcess['reason'],
                    'filename' => $filename
                ];
            }
            
            // Versuche Verarbeitung mit bevorzugter Methode
            if ($canProcess['needsImageMagick'] && self::hasImageMagick()) {
                $result = self::reworkFileWithImageMagick($filename, $maxWidth, $maxHeight, $canProcess['needsConversion']);
                if ($result) {
                    return ['success' => true, 'skipped' => false, 'method' => 'ImageMagick', 'filename' => $filename];
                }
            }
            
            // Fallback zu Media Manager (GD) - aber nicht für Konvertierungsformate
            if (self::hasGD() && !$canProcess['needsConversion']) {
                $result = self::reworkFile($filename, $maxWidth, $maxHeight);
                if ($result) {
                    return ['success' => true, 'skipped' => false, 'method' => 'GD/MediaManager', 'filename' => $filename];
                }
            }
            
            return [
                'success' => false,
                'skipped' => false,
                'error' => 'Keine Bildverarbeitungsbibliothek verfügbar',
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            rex_logger::logException($e);
            return [
                'success' => false,
                'skipped' => false,
                'error' => $e->getMessage(),
                'filename' => $filename
            ];
        }
    }
    
    /**
     * Verarbeitet Bild mit ImageMagick
     *
     * @param string $filename
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @param bool $convertFormat Ob das Format konvertiert werden soll (TIF->JPEG)
     * @return bool
     */
    public static function reworkFileWithImageMagick(string $filename, ?int $maxWidth = null, ?int $maxHeight = null, bool $convertFormat = false): bool
    {
        if (!self::hasImageMagick()) {
            return false;
        }
        
        $media = rex_media::get($filename);
        if ($media == null || !$media->isImage()) {
            return false;
        }
        
        if (is_null($maxWidth) || is_null($maxHeight)) {
            $maxWidth = (int)rex_addon::get('uploader')->getConfig('image-max-width', 0);
            $maxHeight = (int)rex_addon::get('uploader')->getConfig('image-max-height', 0);
        }
        
        $imagePath = rex_path::media($filename);
        $imageSizes = getimagesize($imagePath);
        
        if (
            !is_array($imageSizes) ||
            $imageSizes[0] == 0 ||
            $imageSizes[1] == 0 ||
            ($maxWidth == 0 && $maxHeight == 0) ||
            (
                ($maxWidth == 0 || $imageSizes[0] <= $maxWidth) &&
                ($maxHeight == 0 || $imageSizes[1] <= $maxHeight)
            )
        ) {
            return false;
        }
        
        try {
            if (class_exists('Imagick')) {
                return self::processWithImagickExtension($filename, $maxWidth, $maxHeight, $imagePath, $convertFormat);
            } else {
                return self::processWithImageMagickBinary($filename, $maxWidth, $maxHeight, $imagePath, $convertFormat);
            }
        } catch (Exception $e) {
            rex_logger::logException($e);
            return false;
        }
    }
    
    /**
     * Verarbeitung mit Imagick PHP Extension
     */
    private static function processWithImagickExtension(string $filename, int $maxWidth, int $maxHeight, string $imagePath, bool $convertFormat = false): bool
    {
        if (!class_exists('Imagick')) {
            return false;
        }
        
        try {
            $imagick = new \Imagick($imagePath);
            $extension = strtolower(rex_file::extension($filename));
            
            // Konvertierung nur wenn explizit erlaubt
            if ($convertFormat && in_array($extension, ['tif', 'tiff'])) {
                // TIF zu JPEG konvertieren
                $imagick->setImageFormat('JPEG');
                $imagick->setImageCompressionQuality(90);
                
                // Neuen Dateinamen mit .jpg Extension erstellen
                $newFilename = rex_file::nameOutput($filename, 'jpg');
                $newImagePath = rex_path::media($newFilename);
                
                // Auto-orientierung und Größe anpassen
                $imagick->autoOrientImage();
                $imagick->resizeImage($maxWidth, $maxHeight, \Imagick::FILTER_LANCZOS, 1, true);
                
                // Als JPEG speichern
                $imagick->writeImage($newImagePath);
                
                // Originaldatei löschen
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                
                // Datenbankupdate mit neuem Dateinamen
                $newSize = $imagick->getImageGeometry();
                $fileSize = filesize($newImagePath);
                
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTablePrefix() . 'media');
                $sql->setWhere(['filename' => $filename]);
                $sql->setValue('filename', $newFilename);
                $sql->setValue('filetype', 'image/jpeg');
                $sql->setValue('filesize', $fileSize);
                $sql->setValue('width', $newSize['width']);
                $sql->setValue('height', $newSize['height']);
                $sql->update();
                
                rex_media_cache::delete($filename);
                rex_media_cache::delete($newFilename);
                
            } else {
                // Normale Verarbeitung ohne Formatwechsel
                $imagick->autoOrientImage();
                $imagick->resizeImage($maxWidth, $maxHeight, \Imagick::FILTER_LANCZOS, 1, true);
                
                // Kompression für JPEG
                if ($imagick->getImageFormat() === 'JPEG') {
                    $imagick->setImageCompressionQuality(85);
                }
                
                // Datei speichern
                $imagick->writeImage($imagePath);
                
                // Datenbankupdate
                $newSize = $imagick->getImageGeometry();
                $fileSize = filesize($imagePath);

                self::updateMediaAndDeleteCache($filename, $fileSize, $newSize['width'], $newSize['height']);
            }

            $imagick->clear();
            return true;
            
        } catch (Exception $e) {
            rex_logger::logException($e);
            return false;
        }
    }
    
    /**
     * Verarbeitung mit ImageMagick Binary
     */
    private static function processWithImageMagickBinary(string $filename, int $maxWidth, int $maxHeight, string $imagePath, bool $convertFormat = false): bool
    {
        $convertPath = self::getConvertPath();
        if (empty($convertPath)) {
            return false;
        }
        
        $extension = strtolower(rex_file::extension($filename));
        $tempPath = $imagePath . '.tmp';
        
        try {
            if ($convertFormat && in_array($extension, ['tif', 'tiff'])) {
                // TIF zu JPEG konvertieren
                $newFilename = rex_file::nameOutput($filename, 'jpg');
                $newImagePath = rex_path::media($newFilename);
                
                $convertCmd = sprintf(
                    '%s %s -auto-orient -resize %dx%d> -quality 90 -format JPEG %s',
                    escapeshellarg($convertPath),
                    escapeshellarg($imagePath),
                    $maxWidth,
                    $maxHeight,
                    escapeshellarg($tempPath)
                );
                
                $output = [];
                $returnVar = 0;
                exec($convertCmd . ' 2>&1', $output, $returnVar);
                
                if ($returnVar !== 0 || !file_exists($tempPath)) {
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    return false;
                }
                
                // Ersetze Original mit konvertierter Datei
                if (!rename($tempPath, $newImagePath)) {
                    unlink($tempPath);
                    return false;
                }
                
                // Originaldatei löschen
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                
                // Update DB record to new filename and format
                $newImageSizes = getimagesize($newImagePath);
                $fileSize = filesize($newImagePath);
                
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTablePrefix() . 'media');
                $sql->setWhere(['filename' => $filename]);
                $sql->setValue('filename', $newFilename);
                $sql->setValue('filetype', 'image/jpeg');
                $sql->setValue('filesize', $fileSize);
                $sql->setValue('width', $newImageSizes[0]);
                $sql->setValue('height', $newImageSizes[1]);
                $sql->update();
                
                rex_media_cache::delete($filename);
                rex_media_cache::delete($newFilename);
                
            } else {
                // Normale Verarbeitung ohne Formatwechsel
                $convertCmd = sprintf(
                    '%s %s -auto-orient -resize %dx%d> -quality 85 %s',
                    escapeshellarg($convertPath),
                    escapeshellarg($imagePath),
                    $maxWidth,
                    $maxHeight,
                    escapeshellarg($tempPath)
                );
                
                $output = [];
                $returnVar = 0;
                exec($convertCmd . ' 2>&1', $output, $returnVar);
                
                if ($returnVar !== 0 || !file_exists($tempPath)) {
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    return false;
                }
                
                // Ersetze Original
                if (!rename($tempPath, $imagePath)) {
                    unlink($tempPath);
                    return false;
                }
                
                // Update Datenbank
                $newImageSizes = getimagesize($imagePath);
                $fileSize = filesize($imagePath);
                
                self::updateMediaAndDeleteCache($filename, $fileSize, $newImageSizes[0], $newImageSizes[1]);
            }
            
            return true;
            
        } catch (Exception $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            rex_logger::logException($e);
            return false;
        }
    }
    
    /**
     * Löscht abgeschlossene Batch-Dateien die älter als 1 Stunde sind
     */
    public static function cleanupOldBatches(): void
    {
        $cacheDir = rex_path::addonCache('uploader');
        $files = glob($cacheDir . self::BATCH_CACHE_KEY . '*.json');
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - 3600) { // älter als 1 Stunde
                unlink($file);
            }
        }
    }
    /**
     * rework image file with uploader addon max size settings
     *
     * @param string $filename
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @return bool
     * @throws \rex_sql_exception
     * @author Peter Schulze | p.schulze[at]bitshifters.de
     * @created 05.06.2025
     */
    public static function reworkFile(string $filename, ?int $maxWidth = null, ?int $maxHeight = null): bool
    {
        $media = rex_media::get($filename);

        if($media == null || !$media->isImage())
        {
            return false;
        }

        if(is_null($maxWidth) || is_null($maxHeight))
        {
            $maxWidth = (int)rex_addon::get('uploader')->getConfig('image-max-width', 0);
            $maxHeight = (int)rex_addon::get('uploader')->getConfig('image-max-height', 0);
        }

        $imageSizes = getimagesize(rex_path::media($filename));

        if(
            !is_array($imageSizes) ||
            $imageSizes[0] == 0 ||
            $imageSizes[1] == 0 ||
            ($maxWidth == 0 && $maxHeight == 0) ||
            (
                ($maxWidth == 0 || $imageSizes[0] <= $maxWidth) &&
                ($maxHeight == 0 || $imageSizes[1] <= $maxHeight)
            )
        )
        {
            return false;
        }

        $cachePath = rex_path::addonCache('media_manager');

        $rexmedia = new rex_managed_media(rex_path::media($filename));
        $manager = new rex_media_manager($rexmedia);
        $manager->setCachePath($cachePath);

        $effect = new rex_effect_resize();
        $effect->setMedia($rexmedia);
        $effect->setParams([
            'allow_enlarge' => 'not_enlarge',
            'style' => 'maximum',
            'width' => $maxWidth,
            'height' => $maxHeight,
        ]);
        $effect->execute();
        $rescaledFilesize = (int)rex_string::size($rexmedia->getSource());

        // replace file in media folder
        rex_file::put(rex_path::media($filename), $rexmedia->getSource());

        self::updateMediaAndDeleteCache($filename, $rescaledFilesize, $rexmedia->getWidth(), $rexmedia->getHeight());

        return true;
    }

    /**
     * Aktualisiert die Datenbank und löscht den Cache für ein Medium 
     */
    public static function updateMediaAndDeleteCache(string $filename, int $filesize = 0, int $width = 0, int $height = 0): void 
    {
        $saveObject = rex_sql::factory();
        $saveObject->setTable(rex::getTablePrefix() . 'media');
        $saveObject->setWhere(['filename' => $filename]);
        $saveObject->setValue('filesize', $filesize);
        $saveObject->setValue('width', $width);
        $saveObject->setValue('height', $height);
        $saveObject->update();
        
        rex_media_cache::delete($filename);
    }

    /**
     * Adjusts the brightness of a given hexadecimal color by a specified factor.
     *
     * @param string $hexColor The hexadecimal color code to be modified (with or without a leading '#').
     *                         The color code can be in 3-digit or 6-digit format.
     * @param float $factor The factor by which to adjust the brightness.
     *                      A value between 0 and 1 darkens the color,
     *                      a value above 1 up to 2 lightens the color.
     * @return string The modified hexadecimal color code in 6-digit format with a leading '#'.
     */
    public static function lightenColor(string $hexColor, float $factor = 1.0): string
    {
        $color = ltrim($hexColor, '#');

        if (strlen($color) == 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        $factor = max(0, min(2, $factor));

        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        if ($factor < 1) {
            $r = round($r * $factor);
            $g = round($g * $factor);
            $b = round($b * $factor);
        } elseif ($factor > 1) {
            $faktorAngepasst = $factor - 1; // 0-1 Bereich
            $r = round($r + (255 - $r) * $faktorAngepasst);
            $g = round($g + (255 - $g) * $faktorAngepasst);
            $b = round($b + (255 - $b) * $faktorAngepasst);
        }

        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}