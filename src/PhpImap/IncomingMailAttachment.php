<?php

namespace PhpImap;

use finfo;

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

    /**
     * @var string
     */
    private $mimeType;

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

    /**
     * Sets the file path.
     *
     * @param string $filePath File path incl. file name and optional extension
     *
     * @return void
     */
    public function setFilePath($filePath)
    {
        $this->file_path = $filePath;
    }

    /**
     * Sets the data part info.
     *
     * @param DataPartInfo $dataInfo Date info (file content)
     *
     * @return void
     */
    public function addDataPartInfo(DataPartInfo $dataInfo)
    {
        $this->dataInfo = $dataInfo;
    }

    /**
     * Gets the MIME type.
     *
     * @return string
     */
    public function getMimeType()
    {
        if (!$this->mimeType) {
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME);

                $this->mimeType = $finfo->buffer($this->getContents());
            }
        }

        return $this->mimeType;
    }

    /**
     * Gets the file content.
     *
     * @return string
     */
    public function getContents()
    {
        return $this->dataInfo->fetch();
    }

    /**
     * Saves the attachment object on the disk.
     *
     * @return bool True, if it could save the attachment on the disk
     */
    public function saveToDisk()
    {
        if (null === $this->dataInfo) {
            return false;
        }

        if (false === file_put_contents($this->filePath, $this->dataInfo->fetch())) {
            unset($this->filePath);
            unset($this->file_path);

            return false;
        }

        return true;
    }
}
