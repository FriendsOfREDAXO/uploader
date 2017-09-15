<?php
class jquery_file_upload_meta
{
    public static function save($params)
    {
        $handler = new jquery_file_upload_metainfo_handler();
        $handler->save($params);
    }
}