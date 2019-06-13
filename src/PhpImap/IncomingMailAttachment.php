<?php

namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @property string $filePath lazy attachment data file
 */
class IncomingMailAttachment
{
    public $id;
    public $contentId;
    public $name;
    public $disposition;
    public $charset;
    public $emlOrigin;
    private $file_path;
    private $dataInfo;

    public function __get($name)
    {
        if ('filePath' !== $name) {
            trigger_error("Undefined property: IncomingMailAttachment::$name");
        }

        if (!isset($this->file_path)) {
            return false;
        }

        $this->filePath = $this->file_path;

        if (@file_exists($this->file_path)) {
            return $this->filePath;
        }

        return $this->filePath;
    }

    public function setFilePath($filePath)
    {
        $this->file_path = $filePath;
    }

    public function addDataPartInfo(DataPartInfo $dataInfo)
    {
        $this->dataInfo = $dataInfo;
    }

    /*
     * Saves the attachment object on the disk
     * @return boolean True, if it could save the attachment on the disk
    */
    public function saveToDisk()
    {
        if (false === file_put_contents($this->filePath, $this->dataInfo->fetch())) {
            unset($this->filePath);
            unset($this->file_path);

            return false;
        }

        return true;
    }
}
