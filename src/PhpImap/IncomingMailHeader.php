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
	public $subject;

	public $fromName;
	public $fromAddress;

	public $to = array();
	public $toString;
	public $cc = array();
	public $bcc = array();
	public $replyTo = array();

	public $messageId;
}
