<?php namespace PhpImap;

abstract class AbstractMailAttachment
{
    public $id;
    public $contentId;
    public $name;
    public $filePath;
    public $disposition;

    /**
     * Function from save file
     *
     * @param $content
     * @return mixed
     */
    public abstract function save ($content);
}