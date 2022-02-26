<?php

declare(strict_types=1);

namespace PhpImap;

use const FILEINFO_NONE;
use finfo;
use UnexpectedValueException;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @property null|false|string $filePath lazy attachment data file
 *
 * @psalm-type fileinfoconst = 0|2|16|1024|1040|8|32|128|256|16777216
 */
class IncomingMailAttachment
{
    /** @var null|string */
    public $id;

    /** @var null|string */
    public $contentId;

    /** @var null|int */
    public $type;

    /** @var null|int */
    public $encoding;

    /** @var null|string */
    public $subtype;

    /** @var null|string */
    public $description;

    /** @var null|string */
    public $name;

    /** @var null|int */
    public $sizeInBytes;

    /** @var null|string */
    public $disposition;

    /** @var null|string */
    public $charset;

    /** @var null|bool */
    public $emlOrigin;

    /** @var null|string */
    public $fileInfoRaw;

    /** @var null|string */
    public $fileInfo;

    /** @var null|string */
    public $mime;

    /** @var null|string */
    public $mimeEncoding;

    /** @var null|string */
    public $fileExtension;

    /** @var null|string */
    public $mimeType;

    /** @var null|string */
    private $file_path;

    /** @var null|DataPartInfo */
    private $dataInfo;

    /** @var null|string */
    private $filePath;

    /**
     * @return false|string
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
     * @param int $fileinfo_const Any predefined constant. See https://www.php.net/manual/en/fileinfo.constants.php
     *
     * @psalm-param fileinfoconst $fileinfo_const
     */
    public function getFileInfo(int $fileinfo_const = FILEINFO_NONE): string
    {
        $finfo = new finfo($fileinfo_const);

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
