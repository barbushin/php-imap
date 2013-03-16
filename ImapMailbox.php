<?php

/**
 * @see https://github.com/barbushin/php-imap
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 */
class ImapMailbox {

	protected $imapPath;
	protected $login;
	protected $password;
	protected $serverEncoding;
	protected $attachmentsDir;

	public function __construct($imapPath, $login, $password, $attachmentsDir = null, $serverEncoding = 'utf-8') {
		$this->imapPath = $imapPath;
		$this->login = $login;
		$this->password = $password;
		$this->serverEncoding = $serverEncoding;
		if($attachmentsDir) {
			if(!is_dir($attachmentsDir)) {
				throw new Exception('Directory "' . $attachmentsDir . '" not found');
			}
			$this->attachmentsDir = rtrim(realpath($attachmentsDir), '\\/');
		}
	}

	/**
	 * Get IMAP mailbox connection stream
	 * @param bool $forceConnection Initialize connection if it's not initialized
	 * @return null|resource
	 */
	public function getImapStream($forceConnection = true) {
		static $imapStream;
		if($forceConnection) {
			if($imapStream && !imap_ping($imapStream)) {
				$this->disconnect();
				$imapStream = null;
			}
			if(!$imapStream) {
				$imapStream = $this->initImapStream();
			}
		}
		return $imapStream;
	}

	protected function initImapStream() {
		$imapStream = @imap_open($this->imapPath, $this->login, $this->password);
		if(!$imapStream) {
			throw new ImapMailboxException('Connection error: ' . imap_last_error());
		}
		return $imapStream;
	}

	protected function disconnect() {
		$imapStream = $this->getImapStream(false);
		if($imapStream) {
			$this->expungeDeletedMessages();
			$errors = imap_errors();
			if($errors) {
				foreach($errors as $error) {
					trigger_error($error);
				}
			}
			imap_close($imapStream);
		}
	}

	/*
	 * Get information about the current mailbox.
	 *
	 * Returns the information in an object with following properties:
	 *	Date - current system time formatted according to RFC2822
	 *	Driver - protocol used to access this mailbox: POP3, IMAP, NNTP
	 *	Mailbox - the mailbox name
	 *	Nmsgs - number of messages in the mailbox
	 *	Recent - number of recent messages in the mailbox
	 *
	 * @return stdClass
	 */
	public function checkMailbox() {
		return imap_check($this->getImapStream());
	}

	/*
	 * This function performs a search on the mailbox currently opened in the given IMAP stream.
	 * For example, to match all unanswered messages sent by Mom, you'd use: "UNANSWERED FROM mom".
	 * Searches appear to be case insensitive. This list of criteria is from a reading of the UW
	 * c-client source code and may be incomplete or inaccurate (see also RFC2060, section 6.4.4).
	 *
	 * criteria
	 *	A string, delimited by spaces, in which the following keywords are allowed. Any multi-word arguments (e.g. FROM "joey smith") must be quoted. Results will match all criteria entries.
	 *		ALL - return all messages matching the rest of the criteria
	 *		ANSWERED - match messages with the \\ANSWERED flag set
	 *		BCC "string" - match messages with "string" in the Bcc: field
	 *		BEFORE "date" - match messages with Date: before "date"
	 *		BODY "string" - match messages with "string" in the body of the message
	 *		CC "string" - match messages with "string" in the Cc: field
	 *		DELETED - match deleted messages
	 *		FLAGGED - match messages with the \\FLAGGED (sometimes referred to as Important or Urgent) flag set
	 *		FROM "string" - match messages with "string" in the From: field
	 *		KEYWORD "string" - match messages with "string" as a keyword
	 *		NEW - match new messages
	 *		OLD - match old messages
	 *		ON "date" - match messages with Date: matching "date"
	 *		RECENT - match messages with the \\RECENT flag set
	 *		SEEN - match messages that have been read (the \\SEEN flag is set)
	 *		SINCE "date" - match messages with Date: after "date"
	 *		SUBJECT "string" - match messages with "string" in the Subject:
	 *		TEXT "string" - match messages with text "string"
	 *		TO "string" - match messages with "string" in the To:
	 *		UNANSWERED - match messages that have not been answered
	 *		UNDELETED - match messages that are not deleted
	 *		UNFLAGGED - match messages that are not flagged
	 *		UNKEYWORD "string" - match messages that do not have the keyword "string"
	 *		UNSEEN - match messages which have not been read yet
	 *
	 * @return array Mails ids
	 */
	public function searchMailbox($criteria = 'ALL') {
		$mailsIds = imap_search($this->getImapStream(), $criteria, SE_UID, $this->serverEncoding);
		return $mailsIds ? $mailsIds : array();
	}

	/*
	 * Marks messages listed in mailId for deletion.
	 * @return bool
	 */
	public function deleteMessage($mailId, $purge_deleted = false) {
		return imap_delete($this->getImapStream(), $mailId, FT_UID);
	}

	/*
	 * Deletes all the messages marked for deletion by imap_delete(), imap_mail_move(), or imap_setflag_full().
	 * @return bool
	 */
	public function expungeDeletedMessages() {
		return imap_expunge($this->getImapStream());
	}

	public function markMessageAsRead($mailId) {
		$this->setFlag($mailId, '\\Seen');
	}

	public function markMessageAsUnread($mailId) {
		$this->clearFlag($mailId, '\\Seen');
	}

	public function markMessageAsImportant($mailId) {
		$this->setFlag($mailId, '\\Flagged');
	}

	/*
	 * Causes a store to add the specified flag to the flags set for the messages in the specified sequence.
	 *
	 * @param array $mailsIds
	 * @param $flag Flags which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060.
	 * @return bool
	 */
	public function setFlag(array $mailsIds, $flag) {
		return imap_setflag_full($this->getImapStream(), implode(',', $mailsIds), $flag, ST_UID);
	}

	/*
	 * Cause a store to delete the specified flag to the flags set for the messages in the specified sequence.
	 *
	 * @param array $mailsIds
	 * @param $flag Flags which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060.
	 * @return bool
	 */
	public function clearFlag(array $mailsIds, $flag) {
		return imap_clearflag_full($this->getImapStream(), implode(',', $mailsIds), $flag, ST_UID);
	}

	/*
	 * Fetch mail headers for listed mails ids
	 *
	 * Returns an array of objects describing one message header each. The object will only define a property if it exists. The possible properties are:
	 *  subject - the messages subject
	 *  from - who sent it
	 *  to - recipient
	 *  date - when was it sent
	 *  message_id - Message-ID
	 *  references - is a reference to this message id
	 *  in_reply_to - is a reply to this message id
	 *  size - size in bytes
	 *  uid - UID the message has in the mailbox
	 *  msgno - message sequence number in the mailbox
	 *  recent - this message is flagged as recent
	 *  flagged - this message is flagged
	 *  answered - this message is flagged as answered
	 *  deleted - this message is flagged for deletion
	 *  seen - this message is flagged as already read
	 *  draft - this message is flagged as being a draft
	 *
	 * @param array $mailsIds
	 * @return array
	 */
	public function getMailsInfo(array $mailsIds) {
		return imap_fetch_overview($this->getImapStream(), implode(',', $mailsIds), FT_UID);
	}

	/**
	 * Gets messages ids sorted by some criteria
	 *
	 * Criteria can be one (and only one) of the following constants:
	 *  SORTDATE - message Date
	 *  SORTARRIVAL - arrival date (default)
	 *  SORTFROM - mailbox in first From address
	 *  SORTSUBJECT - message subject
	 *  SORTTO - mailbox in first To address
	 *  SORTCC - mailbox in first cc address
	 *  SORTSIZE - size of message in octets
	 *
	 * @param int $criteria
	 * @param bool $reverse
	 * @return array Mails ids
	 */
	public function sortMessages($criteria = SORTARRIVAL, $reverse = true) {
		return imap_sort($this->getImapStream(), $criteria, $reverse, SE_UID);
	}

	/**
	 * Get messages count in mail box
	 * @return int
	 */
	public function countMessages() {
		return imap_num_msg($this->getImapStream());
	}

	/**
	 * Get mail data
	 *
	 * @param $mailId
	 * @return IncomingMail
	 */
	public function getMail($mailId) {
		$head = imap_rfc822_parse_headers(imap_fetchheader($this->getImapStream(), $mailId, FT_UID));

		$mail = new IncomingMail();
		$mail->id = $mailId;
		$mail->date = date('Y-m-d H:i:s', isset($head->date) ? strtotime($head->date) : time());
		$mail->subject = $this->decodeMimeStr($head->subject);
		$mail->fromName = isset($head->from[0]->personal) ? $this->decodeMimeStr($head->from[0]->personal) : null;
		$mail->fromAddress = strtolower($head->from[0]->mailbox . '@' . $head->from[0]->host);

		$toStrings = array();
		foreach($head->to as $to) {
			$toEmail = strtolower($to->mailbox . '@' . $to->host);
			$toName = isset($to->personal) ? $this->decodeMimeStr($to->personal) : null;
			$toStrings[] = $toName ? "$toName <$toEmail>" : $toEmail;
			$mail->to[$toEmail] = $toName;
		}
		$mail->toString = implode(', ', $toStrings);

		if(isset($head->cc)) {
			foreach($head->cc as $cc) {
				$mail->cc[strtolower($cc->mailbox . '@' . $cc->host)] = isset($cc->personal) ? $this->decodeMimeStr($cc->personal) : null;
			}
		}

		if(isset($head->reply_to)) {
			foreach($head->reply_to as $replyTo) {
				$mail->replyTo[strtolower($replyTo->mailbox . '@' . $replyTo->host)] = isset($replyTo->personal) ? $this->decodeMimeStr($replyTo->personal) : null;
			}
		}

		$mailStructure = imap_fetchstructure($this->getImapStream(), $mailId, FT_UID);

		if(empty($mailStructure->parts)) {
			$this->initMailPart($mail, $mailStructure, 0);
		}
		else {
			foreach($mailStructure->parts as $partNum => $partStruct) {
				$this->initMailPart($mail, $partStruct, $partNum + 1);
			}
		}

		$mail->textHtmlOriginal = $mail->textHtml;

		return $mail;
	}

	protected function quoteAttachmentFilename($filename) {
		$replace = array(
			'/\s/' => '_',
			'/[^0-9a-zA-Z_\.]/' => '',
			'/_+/' => '_', '/(^_)|(_$)/' => '',
		);
		return preg_replace(array_keys($replace), $replace, $filename);
	}

	protected function initMailPart(IncomingMail $mail, $partStructure, $partNum) {
		$data = $partNum ? imap_fetchbody($this->getImapStream(), $mail->id, $partNum, FT_UID) : imap_body($this->getImapStream(), $mail->id, FT_UID);

		if($partStructure->encoding == 1) {
			$data = imap_utf8($data);
		}
		elseif($partStructure->encoding == 2) {
			$data = imap_binary($data);
		}
		elseif($partStructure->encoding == 3) {
			$data = imap_base64($data);
		}
		elseif($partStructure->encoding == 4) {
			$data = imap_qprint($data);
		}

		$params = array();
		if(!empty($partStructure->parameters)) {
			foreach($partStructure->parameters as $param) {
				$params[strtolower($param->attribute)] = $param->value;
			}
		}
		if(!empty($partStructure->dparameters)) {
			foreach($partStructure->dparameters as $param) {
				$params[strtolower($param->attribute)] = $param->value;
			}
		}
		if(!empty($params['charset'])) {
			$data = iconv($params['charset'], $this->serverEncoding, $data);
		}

		// attachments
		if($this->attachmentsDir) {
			$filename = false;
			$attachmentId = $partStructure->ifid ? trim($partStructure->id, " <>") : null;
			if(empty($params['filename']) && empty($params['name']) && $attachmentId) {
				$filename = $attachmentId . '.' . strtolower($partStructure->subtype);
			}
			elseif(!empty($params['filename']) || !empty($params['name'])) {
				$filename = !empty($params['filename']) ? $params['filename'] : $params['name'];
				$filename = $this->decodeMimeStr($filename);
				$filename = $this->quoteAttachmentFilename($filename);
			}
			if($filename) {
				if($this->attachmentsDir) {
					$filePath = $this->attachmentsDir . DIRECTORY_SEPARATOR . preg_replace('~[\\\\/.]~', '', $mail->id) . '_' . $filename;
					file_put_contents($filePath, $data);
					$mail->attachments[$filename] = $filePath;
				}
				else {
					$mail->attachments[$filename] = $filename;
				}
				if($attachmentId) {
					$mail->attachmentsIds[$filename] = $attachmentId;
				}
			}
		}
		if($partStructure->type == 0 && $data) {
			if(strtolower($partStructure->subtype) == 'plain') {
				$mail->textPlain .= $data;
			}
			else {
				$mail->textHtml .= $data;
			}
		}
		elseif($partStructure->type == 2 && $data) {
			$mail->textPlain .= trim($data);
		}
		if(!empty($partStructure->parts)) {
			foreach($partStructure->parts as $subpartNum => $subpartStruct) {
				$this->initMailPart($mail, $subpartStruct, $partNum . '.' . ($subpartNum + 1));
			}
		}
	}

	protected function decodeMimeStr($string, $charset = 'UTF-8') {
		$newString = '';
		$elements = imap_mime_header_decode($string);
		for($i = 0; $i < count($elements); $i++) {
			if($elements[$i]->charset == 'default') {
				$elements[$i]->charset = 'iso-8859-1';
			}
			$newString .= iconv($elements[$i]->charset, $charset, $elements[$i]->text);
		}
		return $newString;
	}

	public function __destruct() {
		$this->disconnect();
	}
}

class IncomingMail {

	public $id;
	public $date;
	public $subject;

	public $fromName;
	public $fromAddress;

	public $to = array();
	public $toString;
	public $cc = array();
	public $replyTo = array();

	public $textPlain;
	public $textHtml;
	public $textHtmlOriginal;
	public $attachments = array();
	public $attachmentsIds = array();

	public function fetchMessageInternalLinks($baseUrl) {
		if($this->textHtml) {
			foreach($this->attachments as $filePath) {
				$filename = basename($filePath);
				if(isset($this->attachmentsIds[$filename])) {
					$this->textHtml = preg_replace('/(<img[^>]*?)src=["\']?ci?d:' . preg_quote($this->attachmentsIds[$filename]) . '["\']?/is', '\\1 src="' . $baseUrl . $filename . '"', $this->textHtml);
				}
			}
		}
	}
}

class ImapMailboxException extends Exception {

}
