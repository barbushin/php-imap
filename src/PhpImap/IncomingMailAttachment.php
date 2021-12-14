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
 * @property string|false|null $filePath lazy attachment data file
 *
 * @psalm-type fileinfoconst = 0|2|16|1024|1040|8|32|128|256|16777216
 */
class IncomingMailAttachment
{
    /** @var string|null */
    public $id;

    /** @var string|null */
    public $contentId;

    /** @var int|null */
    public $type;

    /** @var int|null */
    public $encoding;

    /** @var string|null */
    public $subtype;

    /** @var string|null */
    public $description;

    /** @var string|null */
    public $name;

    /** @var int|null */
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
    public $mimeType;

    /** @var string|null */
    private $file_path;

    /** @var DataPartInfo|null */
    private $dataInfo;

    /** @var string|null */
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
