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
    /** @var int|null The IMAP message ID - not the "Message-ID:"-header of the email */
    public $id;

    /** @var string|null */
    public $imapPath;

    /** @var string|null */
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

    /** @var string|null */
    public $date;

    /** @var string|null */
    public $headersRaw;

    /** @var object|null */
    public $headers;

    /** @var string|null */
    public $mimeVersion;

    /** @var string|null */
    public $xVirusScanned;

    /** @var string|null */
    public $organization;

    /** @var string|null */
    public $contentType;

    /** @var string|null */
    public $xMailer;

    /** @var string|null */
    public $contentLanguage;

    /** @var string|null */
    public $xSenderIp;

    /** @var string|null */
    public $priority;

    /** @var string|null */
    public $importance;

    /** @var string|null */
    public $sensitivity;

    /** @var string|null */
    public $autoSubmitted;

    /** @var string|null */
    public $precedence;

    /** @var string|null */
    public $failedRecipients;

    /** @var string|null */
    public $subject;

    /** @var string|null */
    public $fromHost;

    /** @var string|null */
    public $fromName;

    /** @var string|null */
    public $fromAddress;

    /** @var string|null */
    public $senderHost;

    /** @var string|null */
    public $senderName;

    /** @var string|null */
    public $senderAddress;

    /** @var string|null */
    public $xOriginalTo;

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $to = [];

    /** @var string|null */
    public $toString;

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $cc = [];

    /** @var string|null */
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

    /** @var string|null */
    public $messageId;
}
