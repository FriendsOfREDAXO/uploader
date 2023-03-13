<?php

/** @var rex_addon $this */

if (!$this->hasConfig()) {
    $this->setConfig([
        'image-max-width' => 4000, // px
        'image-max-height' => 4000, // px
        'image-max-filesize' => 30, // MB
        'image-resize-checked' => true, // resize option checked per default?
        'filename-as-title-checked' => false // filename as title option checked per default?
    ]);
}
