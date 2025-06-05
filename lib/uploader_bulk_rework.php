<?php

namespace uploader\lib;

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

/**
 * Class uploader_bulk_rework
 *
 * @category
 * @package uploader\lib
 * @author Peter Schulze | p.schulze[at]bitshifters.de
 * @created 05.06.2025
 */
class uploader_bulk_rework
{
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
        $rescaledFilesize = rex_string::size($rexmedia->getSource());

        // replace file in media folder
        rex_file::put(rex_path::media($filename), $rexmedia->getSource());

        // update filesize and dimensions in database
        $saveObject = rex_sql::factory();
        $saveObject->setTable(rex::getTablePrefix() . 'media');
        $saveObject->setWhere(['filename' => $filename]);
        $saveObject->setValue('filesize', $rescaledFilesize);
        $saveObject->setValue('width', $rexmedia->getWidth());
        $saveObject->setValue('height', $rexmedia->getHeight());
        $saveObject->update();

        rex_media_cache::delete($filename);
        return true;
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