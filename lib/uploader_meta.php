<?php
class uploader_meta
{
    public static function save($params)
    {
        $handler = new uploader_metainfo_handler();
        $handler->save($params);
    }
}