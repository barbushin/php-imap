<?php

declare(strict_types=1);

namespace PhpImap;

use Exception;
use finfo;
use UnexpectedValueException;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @property string $filePath lazy attachment data file
 */
class IncomingMailAttachment
{
    /** @var string|null */
    public $id;

    /** @var string|null */
    public $contentId;

    /** @var integer|null */
    public $type;

    /** @var integer|null */
    public $encoding;

    /** @var string|null */
    public $subtype;

    /** @var string|null */
    public $description;

    /** @var string|null */
    public $name;

    /** @var integer|null */
    public $sizeInBytes;

    /** @var string|null */
    public $disposition;

    /** @var string|null */
    public $charset;

    /** @var bool|null */
    public $emlOrigin;

    /** @var string|null */
    public $fileInfoRaw;

    /** @var string|null */
    public $fileInfo;

    /** @var string|null */
    public $mime;

    /** @var string|null */
    public $mimeEncoding;

    /** @var string|null */
    public $fileExtension;

    /** @var string|null */
    private $file_path;

    /** @var DataPartInfo|null */
    private $dataInfo;

    /** @var string|null */
    private $mimeType;

    /** @var string|null */
    private $filePath;

    /**
     * @return string|false|null
     */
    public function __get(string $name)
    {
        if ('filePath' !== $name) {
            \trigger_error("Undefined property: IncomingMailAttachment::$name");
        }

        if (!isset($this->file_path)) {
            return false;
        }

        $this->filePath = $this->file_path;

        if (@\file_exists($this->file_path)) {
            return $this->filePath;
        }

        return $this->filePath;
    }

    /**
     * Sets the file path.
     *
     * @param string $filePath File path incl. file name and optional extension
     */
    public function setFilePath(string $filePath): void
    {
        $this->file_path = $filePath;
    }

    /**
     * Sets the data part info.
     *
     * @param DataPartInfo $dataInfo Date info (file content)
     */
    public function addDataPartInfo(DataPartInfo $dataInfo): void
    {
        $this->dataInfo = $dataInfo;
    }

    /**
     * Gets information about a file.
     *
     * @param const $fileinfo_const Any predefined constant. See https://www.php.net/manual/en/fileinfo.constants.php
     */
    public function getFileInfo($fileinfo_const = FILEINFO_NONE): string
    {
        if (($fileinfo_const == FILEINFO_MIME) AND ($this->mimeType != false)) {
            return $this->mimeType;
        }

        try {
            $finfo = new finfo($fileinfo_const);
        } catch (Exception $ex) {
            return null;
        }

        if (!$finfo) {
            return null;
        }

        return $finfo->buffer($this->getContents());
    }

    /**
     * Gets the file content.
     */
    public function getContents(): string
    {
        if (null === $this->dataInfo) {
            throw new UnexpectedValueException(static::class.'::$dataInfo has not been set by calling '.self::class.'::addDataPartInfo()');
        }

        return $this->dataInfo->fetch();
    }

    /**
     * Saves the attachment object on the disk.
     *
     * @return bool True, if it could save the attachment on the disk
     */
    public function saveToDisk(): bool
    {
        if (null === $this->dataInfo) {
            return false;
        }

        if (false === \file_put_contents($this->__get('filePath'), $this->dataInfo->fetch())) {
            unset($this->filePath, $this->file_path);

            return false;
        }

        return true;
    }
}
