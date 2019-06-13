<?php

namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class IncomingMailHeader
{
    /** @var int|string $id The IMAP message ID - not the "Message-ID:"-header of the email */
    public $id;
    public $isDraft = false;
    public $date;
    public $headersRaw;
    public $headers;
    public $priority;
    public $importance;
    public $sensitivity;
    public $autoSubmitted;
    public $precedence;
    public $failedRecipients;
    public $subject;

    public $fromHost;
    public $fromName;
    public $fromAddress;
    public $senderHost;
    public $senderName;
    public $senderAddress;

    public $to = [];
    public $toString;
    public $cc = [];
    public $bcc = [];
    public $replyTo = [];

    public $messageId;
}
