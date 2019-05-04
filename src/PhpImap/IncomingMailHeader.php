<?php namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class IncomingMailHeader {

	/** @var int|string $id The IMAP message ID - not the "Message-ID:"-header of the email */
	public $id;
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

	public $to = array();
	public $toString;
	public $cc = array();
	public $bcc = array();
	public $replyTo = array();

	public $messageId;
}
