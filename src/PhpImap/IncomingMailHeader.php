<?php

declare(strict_types=1);

namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class IncomingMailHeader
{
    /** @var null|int The IMAP message ID - not the "Message-ID:"-header of the email */
    public $id;

    /** @var null|string */
    public $imapPath;

    /** @var null|string */
    public $mailboxFolder;

    /** @var bool */
    public $isSeen = false;

    /** @var bool */
    public $isAnswered = false;

    /** @var bool */
    public $isRecent = false;

    /** @var bool */
    public $isFlagged = false;

    /** @var bool */
    public $isDeleted = false;

    /** @var bool */
    public $isDraft = false;

    /** @var null|string */
    public $date;

    /** @var null|string */
    public $headersRaw;

    /** @var null|object */
    public $headers;

    /** @var null|string */
    public $mimeVersion;

    /** @var null|string */
    public $xVirusScanned;

    /** @var null|string */
    public $organization;

    /** @var null|string */
    public $contentType;

    /** @var null|string */
    public $xMailer;

    /** @var null|string */
    public $contentLanguage;

    /** @var null|string */
    public $xSenderIp;

    /** @var null|string */
    public $priority;

    /** @var null|string */
    public $importance;

    /** @var null|string */
    public $sensitivity;

    /** @var null|string */
    public $autoSubmitted;

    /** @var null|string */
    public $precedence;

    /** @var null|string */
    public $failedRecipients;

    /** @var null|string */
    public $subject;

    /** @var null|string */
    public $fromHost;

    /** @var null|string */
    public $fromName;

    /** @var null|string */
    public $fromAddress;

    /** @var null|string */
    public $senderHost;

    /** @var null|string */
    public $senderName;

    /** @var null|string */
    public $senderAddress;

    /** @var null|string */
    public $xOriginalTo;

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $to = [];

    /** @var null|string */
    public $toString;

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $cc = [];

    /** @var null|string */
    public $ccString;

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $bcc = [];

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $replyTo = [];

    /** @var null|string */
    public $messageId;
}
