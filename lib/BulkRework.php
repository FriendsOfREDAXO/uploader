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
    
    // Problematische Formate die nur mit ImageMagick funktionieren
    const IMAGEMAGICK_ONLY_FORMATS = [
        'psd'
    ];
    
    // Übersprungene Formate bei Bulk-Verarbeitung
    const SKIPPED_FORMATS = [
        'tif', 'tiff', 'svg'
    ];
    
    // Batch processing status cache key
    const BATCH_CACHE_KEY = 'uploader_bulk_batch_';
    
    // Maximale parallele Verarbeitung
    const MAX_PARALLEL_PROCESSES = 3;
    
    /**
     * Gibt die maximale Anzahl paralleler Prozesse zurück (konfigurierbar)
     *
     * @return int
     */
    public static function getMaxParallelProcesses(): int
    {
        return (int)rex_addon::get('uploader')->getConfig('bulk-max-parallel', self::MAX_PARALLEL_PROCESSES);
    }
    
    /**
     * Prüft ob ein Bildformat verarbeitet werden kann
     *
     * @param string $filename
     * @return array ['canProcess' => bool, 'needsImageMagick' => bool, 'format' => string]
     */
    public static function canProcessImage(string $filename): array
    {
        $extension = strtolower(rex_file::extension($filename));
        
        $result = [
            'canProcess' => false,
            'needsImageMagick' => false,
            'format' => $extension,
            'reason' => ''
        ];
        
        // Überspringe TIFF und SVG bei Bulk-Verarbeitung
        if (in_array($extension, self::SKIPPED_FORMATS)) {
            $result['reason'] = 'Format wird bei Bulk-Verarbeitung übersprungen';
            return $result;
        }
        
        if (in_array($extension, self::SUPPORTED_FORMATS)) {
            $result['canProcess'] = true;
        } elseif (in_array($extension, self::IMAGEMAGICK_ONLY_FORMATS)) {
            if (self::hasImageMagick()) {
                $result['canProcess'] = true;
                $result['needsImageMagick'] = true;
            } else {
                $result['reason'] = 'Format benötigt ImageMagick';
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
    private static function getConvertPath(): string
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
     * @return string Batch-ID
     */
    public static function startBatchProcessing(array $filenames, ?int $maxWidth = null, ?int $maxHeight = null): string
    {
        $batchId = uniqid('batch_', true);
        
        $batchData = [
            'id' => $batchId,
            'filenames' => $filenames,
            'maxWidth' => $maxWidth,
            'maxHeight' => $maxHeight,
            'total' => count($filenames),
            'processed' => 0,
            'successful' => 0,
            'errors' => [],
            'skipped' => [],
            'status' => 'running',
            'currentFiles' => [], // Array für parallele Verarbeitung
            'processQueue' => array_values($filenames), // Queue der noch zu verarbeitenden Dateien
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
     * Holt erweiterten Status eines Batch-Vorgangs mit zusätzlichen Informationen für die UI
     *
     * @param string $batchId
     * @return array|null
     */
    public static function getBatchStatusExtended(string $batchId): ?array
    {
        $status = self::getBatchStatus($batchId);
        
        if (!$status) {
            return null;
        }
        
        // Berechne Fortschritt
        $progress = $status['total'] > 0 ? round(($status['processed'] / $status['total']) * 100, 1) : 0;
        
        // Berechne geschätzte verbleibende Zeit
        $elapsed = time() - $status['startTime'];
        $remainingTime = null;
        
        if ($status['processed'] > 0 && $elapsed > 0) {
            $avgTimePerFile = $elapsed / $status['processed'];
            $remaining = $status['total'] - $status['processed'];
            $remainingTime = round($avgTimePerFile * $remaining);
        }
        
        // Aktuell verarbeitete Dateien
        $currentlyProcessing = [];
        if (isset($status['currentFiles'])) {
            foreach ($status['currentFiles'] as $process) {
                $currentlyProcessing[] = [
                    'filename' => $process['filename'],
                    'duration' => round(microtime(true) - $process['startTime'], 1)
                ];
            }
        }
        
        return array_merge($status, [
            'progress' => $progress,
            'remainingTime' => $remainingTime,
            'elapsedTime' => $elapsed,
            'currentlyProcessing' => $currentlyProcessing,
            'queueLength' => count($status['processQueue'] ?? []),
            'activeProcesses' => count($status['currentFiles'] ?? [])
        ]);
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
     * Verarbeitet die nächsten Bilder in einem Batch (bis zu MAX_PARALLEL_PROCESSES gleichzeitig)
     *
     * @param string $batchId
     * @return array Status-Update
     */
    public static function processNextBatchItems(string $batchId): array
    {
        $batchStatus = self::getBatchStatus($batchId);
        
        if (!$batchStatus || $batchStatus['status'] !== 'running') {
            return ['status' => 'error', 'message' => 'Batch nicht gefunden oder bereits beendet'];
        }
        
        $processQueue = $batchStatus['processQueue'];
        $currentFiles = $batchStatus['currentFiles'];
        
        // Prüfe ob alle Dateien verarbeitet wurden
        if (empty($processQueue) && empty($currentFiles)) {
            self::updateBatchStatus($batchId, [
                'status' => 'completed',
                'endTime' => time()
            ]);
            return ['status' => 'completed', 'batch' => self::getBatchStatus($batchId)];
        }
        
        // Starte neue Verarbeitungsprozesse bis MAX_PARALLEL_PROCESSES erreicht ist
        $maxParallel = self::getMaxParallelProcesses();
        while (count($currentFiles) < $maxParallel && !empty($processQueue)) {
            $filename = array_shift($processQueue);
            $processId = uniqid('process_', true);
            
            $currentFiles[$processId] = [
                'filename' => $filename,
                'startTime' => microtime(true),
                'status' => 'processing'
            ];
        }
        
        // Verarbeite alle aktuellen Dateien
        $completedProcesses = [];
        $results = [];
        
        foreach ($currentFiles as $processId => $process) {
            if ($process['status'] === 'processing') {
                $result = self::reworkFileWithFallback(
                    $process['filename'], 
                    $batchStatus['maxWidth'], 
                    $batchStatus['maxHeight']
                );
                
                $result['processTime'] = round(microtime(true) - $process['startTime'], 2);
                $results[] = $result;
                $completedProcesses[] = $processId;
            }
        }
        
        // Entferne abgeschlossene Prozesse
        foreach ($completedProcesses as $processId) {
            unset($currentFiles[$processId]);
        }
        
        // Aktualisiere Statistiken
        $updates = [
            'processQueue' => $processQueue,
            'currentFiles' => $currentFiles,
            'processed' => $batchStatus['processed'] + count($results)
        ];
        
        foreach ($results as $result) {
            if ($result['success']) {
                $updates['successful'] = ($batchStatus['successful'] ?? 0) + 1;
            } elseif ($result['skipped']) {
                $updates['skipped'] = array_merge(
                    $batchStatus['skipped'] ?? [], 
                    [$result['filename'] => $result['reason']]
                );
            } else {
                $updates['errors'] = array_merge(
                    $batchStatus['errors'] ?? [], 
                    [$result['filename'] => $result['error'] ?? 'Unbekannter Fehler']
                );
            }
        }
        
        self::updateBatchStatus($batchId, $updates);
        
        return [
            'status' => 'processing',
            'batch' => self::getBatchStatus($batchId),
            'results' => $results,
            'currentlyProcessing' => array_column($currentFiles, 'filename')
        ];
    }
    
    /**
     * Legacy-Methode für Rückwärtskompatibilität
     * 
     * @param string $batchId
     * @return array Status-Update
     * @deprecated Verwende processNextBatchItems() für parallele Verarbeitung
     */
    public static function processNextBatchItem(string $batchId): array
    {
        return self::processNextBatchItems($batchId);
    }
    
    /**
     * Verarbeitet eine Datei mit Fallback-System
     *
     * @param string $filename
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @return array
     */
    public static function reworkFileWithFallback(string $filename, ?int $maxWidth = null, ?int $maxHeight = null): array
    {
        try {
            // Prüfe ob Datei verarbeitet werden kann
            $canProcess = self::canProcessImage($filename);
            
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
                $result = self::reworkFileWithImageMagick($filename, $maxWidth, $maxHeight);
                if ($result) {
                    return ['success' => true, 'skipped' => false, 'method' => 'ImageMagick', 'filename' => $filename];
                }
            }
            
            // Fallback zu Media Manager (GD)
            if (self::hasGD()) {
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
     * @return bool
     */
    public static function reworkFileWithImageMagick(string $filename, ?int $maxWidth = null, ?int $maxHeight = null): bool
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
                return self::processWithImagickExtension($filename, $maxWidth, $maxHeight, $imagePath);
            } else {
                return self::processWithImageMagickBinary($filename, $maxWidth, $maxHeight, $imagePath);
            }
        } catch (Exception $e) {
            rex_logger::logException($e);
            return false;
        }
    }
    
    /**
     * Verarbeitung mit Imagick PHP Extension
     */
    private static function processWithImagickExtension(string $filename, int $maxWidth, int $maxHeight, string $imagePath): bool
    {
        if (!class_exists('Imagick')) {
            return false;
        }
        
        try {
            $imagick = new \Imagick($imagePath);
            
            // Auto-orientierung
            $imagick->autoOrientImage();
            
            // Größe anpassen
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
    private static function processWithImageMagickBinary(string $filename, int $maxWidth, int $maxHeight, string $imagePath): bool
    {
        $tempPath = $imagePath . '.tmp';
        $convertCmd = sprintf(
            'convert %s -auto-orient -resize %dx%d> -quality 85 %s',
            escapeshellarg($imagePath),
            $maxWidth,
            $maxHeight,
            escapeshellarg($tempPath)
        );
        
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
        
        rex_media_cache::delete($filename);
        
        return true;
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