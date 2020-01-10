<?php

namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author nickl- http://github.com/nickl-
 */
class DataPartInfo
{
    const TEXT_PLAIN = 0;
    const TEXT_HTML = 1;

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
     * @param int       $id
     * @param 0|string  $part
     * @param int|mixed $encoding
     * @param int       $options
     */
    public function __construct(Mailbox $mail, $id, $part, $encoding, $options)
    {
        $this->mail = $mail;
        $this->id = $id;
        $this->part = $part;
        $this->encoding = $encoding;
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function fetch()
    {
        if (0 === $this->part) {
            $this->data = Imap::body($this->mail->getImapStream(), $this->id, $this->options);
        } else {
            $this->data = Imap::fetchbody($this->mail->getImapStream(), $this->id, $this->part, $this->options);
        }

        return $this->decodeAfterFetch();
    }

    /**
     * @return string
     */
    protected function decodeAfterFetch()
    {
        switch ($this->encoding) {
            case ENC8BIT:
                $this->data = imap_utf8($this->data);
                break;
            case ENCBINARY:
                $this->data = imap_binary($this->data);
                break;
            case ENCBASE64:
                $this->data = preg_replace('~[^a-zA-Z0-9+=/]+~s', '', $this->data); // https://github.com/barbushin/php-imap/issues/88
                $this->data = imap_base64($this->data);
                break;
            case ENCQUOTEDPRINTABLE:
                $this->data = quoted_printable_decode($this->data);
                break;
        }

        return $this->convertEncodingAfterFetch();
    }

    /**
     * @return string
     */
    protected function convertEncodingAfterFetch()
    {
        if (isset($this->charset) and !empty(trim($this->charset))) {
            $this->data = $this->mail->convertStringEncoding(
                (string) $this->data, // Data to convert
                $this->charset, // FROM-Encoding (Charset)
                $this->mail->getServerEncoding() // TO-Encoding (Charset)
            );
        }

        return (null === $this->data) ? '' : $this->data;
    }
}
