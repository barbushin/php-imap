<?php

declare(strict_types=1);

namespace PhpImap;

use const ENC8BIT;
use const ENCBASE64;
use const ENCBINARY;
use const ENCQUOTEDPRINTABLE;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author nickl- http://github.com/nickl-
 */
class DataPartInfo
{
    public const TEXT_PLAIN = 0;

    public const TEXT_HTML = 1;

    /**
     * @var int
     *
     * @readonly
     */
    public $id;

    /**
     * @var int|mixed
     *
     * @readonly
     */
    public $encoding;

    /** @var string|null */
    public $charset;

    /**
     * @var 0|string
     *
     * @readonly
     */
    public $part;

    /**
     * @var Mailbox
     *
     * @readonly
     */
    public $mail;

    /**
     * @var int
     *
     * @readonly
     */
    public $options;

    /** @var string|null */
    protected $data;

    /**
     * @param 0|string  $part
     * @param int|mixed $encoding
     */
    public function __construct(Mailbox $mail, int $id, $part, $encoding, int $options)
    {
        $this->mail = $mail;
        $this->id = $id;
        $this->part = $part;
        $this->encoding = $encoding;
        $this->options = $options;
    }

    public function fetch(): string
    {
        if (0 === $this->part) {
            $this->data = Imap::body($this->mail->getImapStream(), $this->id, $this->options);
        } else {
            if (null !== $this->data) {
                return $this->data;
            }
            $this->data = Imap::fetchbody($this->mail->getImapStream(), $this->id, $this->part, $this->options);
        }

        return $this->decodeAfterFetch($this->data);
    }

    public function decodeAfterFetch(string $data): string
    {
        switch ($this->encoding) {
            case ENC8BIT:
                $this->data = \imap_utf8((string) $data);
                break;
            case ENCBINARY:
                $this->data = \imap_binary((string) $data);
                break;
            case ENCBASE64:
                $this->data = \base64_decode((string) $data, false);
                break;
            case ENCQUOTEDPRINTABLE:
                $this->data = \quoted_printable_decode((string) $data);
                break;
        }

        return $this->convertEncodingAfterFetch();
    }

    protected function convertEncodingAfterFetch(): string
    {
        if (isset($this->charset) && !empty(\trim($this->charset))) {
            $this->data = $this->mail->decodeMimeStr(
                (string) $this->data // Data to convert
            );

            $this->data = $this->mail->convertToUtf8(
                $this->data,
                $this->charset
            );
        }

        return (null === $this->data) ? '' : $this->data;
    }
}
