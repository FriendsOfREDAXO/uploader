<?php

namespace FriendsOfRedaxo\Uploader;

use rex;
use rex_file;
use rex_media;
use rex_media_cache;
use rex_path;
use rex_sql;
use Imagick;
use ImagickException;

/**
 * Handles image resizing for single-file update/replace operations in the mediapool.
 *
 * Resize priority: ImageMagick (Imagick extension) → GD
 * Does not depend on the mediapool_tools addon.
 */
class ImageResizer
{
    /**
     * Resizes an image file if it exceeds the given max dimensions.
     * Tries ImageMagick first, falls back to GD.
     * Updates the database record and media cache on success.
     *
     * @param string $filename  Mediapool filename (not a full path)
     * @param int    $maxWidth  Maximum width in pixels; 0 = no limit
     * @param int    $maxHeight Maximum height in pixels; 0 = no limit
     * @return bool true if the image was actually resized and saved
     */
    public static function resizeIfNeeded(string $filename, int $maxWidth, int $maxHeight): bool
    {
        $media = rex_media::get($filename);
        if ($media === null || !$media->isImage()) {
            return false;
        }

        $imagePath = rex_path::media($filename);
        $imageSizes = getimagesize($imagePath);

        if (
            !is_array($imageSizes) ||
            $imageSizes[0] === 0 ||
            $imageSizes[1] === 0 ||
            ($maxWidth === 0 && $maxHeight === 0) ||
            (
                ($maxWidth === 0 || $imageSizes[0] <= $maxWidth) &&
                ($maxHeight === 0 || $imageSizes[1] <= $maxHeight)
            )
        ) {
            return false;
        }

        $success = class_exists('Imagick')
            ? self::resizeWithImageMagick($imagePath, $maxWidth, $maxHeight)
            : self::resizeWithGd($imagePath, $maxWidth, $maxHeight);

        if ($success) {
            self::updateMediaInfo($filename);
        }

        return $success;
    }

    /**
     * Resize using the Imagick PHP extension.
     * Handles all formats Imagick supports, including multi-frame GIFs.
     */
    private static function resizeWithImageMagick(string $imagePath, int $maxWidth, int $maxHeight): bool
    {
        try {
            $imagick = new Imagick($imagePath);

            // Handle multi-frame images (animated GIF, multi-page TIFF, etc.)
            $imagick = $imagick->coalesceImages();

            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            [$newWidth, $newHeight] = self::calculateDimensions($width, $height, $maxWidth, $maxHeight);

            if ($newWidth === $width && $newHeight === $height) {
                $imagick->destroy();
                return false;
            }

            foreach ($imagick as $frame) {
                $frame->thumbnailImage($newWidth, $newHeight, false, false);
            }

            $imagick = $imagick->deconstructImages();
            $success = $imagick->writeImages($imagePath, true);
            $imagick->destroy();

            return $success;
        } catch (ImagickException $e) {
            // Fall through to GD on Imagick failure
            return self::resizeWithGd($imagePath, $maxWidth, $maxHeight);
        }
    }

    /**
     * Resize using GD. Supports JPEG, PNG, GIF, WebP.
     */
    private static function resizeWithGd(string $imagePath, int $maxWidth, int $maxHeight): bool
    {
        $content = rex_file::get($imagePath);
        if (!is_string($content)) {
            return false;
        }

        $image = imagecreatefromstring($content);
        if (!$image) {
            return false;
        }

        $currentWidth = imagesx($image);
        $currentHeight = imagesy($image);

        [$newWidth, $newHeight] = self::calculateDimensions($currentWidth, $currentHeight, $maxWidth, $maxHeight);

        if ($newWidth === $currentWidth && $newHeight === $currentHeight) {
            imagedestroy($image);
            return false;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG/WebP/GIF
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);

        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $success = false;

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($newImage, $imagePath, 85);
                break;
            case 'png':
                $success = imagepng($newImage, $imagePath);
                break;
            case 'gif':
                $success = imagegif($newImage, $imagePath);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    $success = imagewebp($newImage, $imagePath);
                }
                break;
        }

        imagedestroy($image);
        imagedestroy($newImage);

        return $success;
    }

    /**
     * Calculates new dimensions while preserving aspect ratio.
     *
     * @return array{0: int, 1: int} [newWidth, newHeight]
     */
    private static function calculateDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $newWidth = $width;
        $newHeight = $height;

        if ($maxWidth > 0 && $newWidth > $maxWidth) {
            $newHeight = (int) ($newHeight * ($maxWidth / $newWidth));
            $newWidth = $maxWidth;
        }

        if ($maxHeight > 0 && $newHeight > $maxHeight) {
            $newWidth = (int) ($newWidth * ($maxHeight / $newHeight));
            $newHeight = $maxHeight;
        }

        return [$newWidth, $newHeight];
    }

    /**
     * Clears the media cache and updates filesize/dimensions in the database.
     *
     * @param string $filename Mediapool filename
     */
    private static function updateMediaInfo(string $filename): void
    {
        rex_media_cache::delete($filename);

        $imagePath = rex_path::media($filename);
        if (!file_exists($imagePath)) {
            return;
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media'));
        $sql->setWhere(['filename' => $filename]);

        $filesize = filesize($imagePath);
        $size = getimagesize($imagePath);

        if (false !== $filesize) {
            $sql->setValue('filesize', $filesize);
        }
        if ($size) {
            $sql->setValue('width', $size[0]);
            $sql->setValue('height', $size[1]);
        }

        $sql->update();
    }
}
