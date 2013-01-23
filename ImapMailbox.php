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
	protected $mbox;
	protected $serverEncoding;
	protected $attachmentsDir;

	public function __construct($imapPath, $login, $password, $attachmentsDir = false, $serverEncoding = 'utf-8') {
		$this->imapPath = $imapPath;
		$this->login = $login;
		$this->password = $password;
		$this->serverEncoding = $serverEncoding;
		if ($attachmentsDir) {
			if (!is_dir($attachmentsDir)) {
				throw new Exception('Directory "' . $attachmentsDir . '" not found');
			}
			$this->attachmentsDir = realpath($attachmentsDir);
		}

		$this->connect();
	}

	/*
	 * Connect to IMAP mailbox
	 */
	public function connect() {
		$this->mbox = @imap_open($this->imapPath, $this->login, $this->password);
		if (!$this->mbox) {
			throw new ImapMailboxException('Connection error: ' . imap_last_error());
		}
		return true;
	}

	/*
	 * CLose IMAP connection
	 */
	public function disconnect() {
		if ($this->mbox) {
			$this->expungeDeletedMessages();
			$errors = imap_errors();
			if ($errors) {
				foreach ($errors as $error) {
					trigger_error($error);
				}
			}
			imap_close($this->mbox);
			$this->mbox = null;
		}
	}

	/*
	 * Pings the stream to see if it's still active. It may discover new mail;
	 * this is the preferred method for a periodic "new mail check" as well as a "keep alive"
	 * for servers which have inactivity timeout.
	 *
	 * Returns TRUE if the stream is still alive, attempts to reconnect otherwise.
	 */
	public function pingMailbox() {
		if (!imap_ping($this->mbox)) {
			return $this->reconnect();
		}
		return true;
	}

	/*
	 * Re-connect to IMAP mailbox
	 */
	public function reconnect() {
		$this->disconnect();
		return $this->connect();
	}

	/*
	 * object checkMailbox ( )
	 * 
	 * Checks information about the current mailbox.
	 *
	 * Returns the information in an object with following properties:
	 *	Date - current system time formatted according to » RFC2822
	 *	Driver - protocol used to access this mailbox: POP3, IMAP, NNTP
	 *	Mailbox - the mailbox name
	 *	Nmsgs - number of messages in the mailbox
	 *	Recent - number of recent messages in the mailbox
	 * Returns FALSE on failure.
	 */
	public function checkMailbox() {
		$this->pingMailbox();
		return imap_check($this->mbox);
	}

	/*
	 * array searchMailbox ( string $criteria  )
	 *
	 * This function performs a search on the mailbox currently opened in the given IMAP stream.
	 * For example, to match all unanswered messages sent by Mom, you'd use: "UNANSWERED FROM mom".
	 * Searches appear to be case insensitive. This list of criteria is from a reading of the UW
	 * c-client source code and may be incomplete or inaccurate (see also » RFC2060, section 6.4.4).
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
	 * Returns an array of message numbers or UIDs.
	 .* Return FALSE if it does not understand the search criteria or no messages have been found.
	 */
	public function searchMailbox($criteria = 'ALL') {
		$this->pingMailbox();
		$mailsIds = imap_search($this->mbox, $criteria, SE_UID, $this->serverEncoding);
		return $mailsIds ? $mailsIds : array();
	}

	/*
	 * bool undeleteMessage (int $msg_number )
	 *
	 * Removes the deletion flag for a specified message, which is set by imap_delete() or imap_mail_move().
	 *
	 * msg_number
	 *	The message number
	 */
	public function undeleteMessage($msg_number) {
		$this->pingMailbox();
		return imap_delete($this->mbox, $msg_number, FT_UID);
	}

	/*
	 * bool deleteMessage (int $msg_number )
	 *
	 * Marks messages listed in msg_number for deletion.
	 *
	 * msg_number
	 *	The message number
	 */
	public function deleteMessage($msg_number, $purge_deleted = false) {
		$this->pingMailbox();
		return imap_delete($this->mbox, $msg_number, FT_UID);
	}

	/*
	 * bool expungeDeletedMessages ( )
	 *
	 * Deletes all the messages marked for deletion by imap_delete(), imap_mail_move(), or imap_setflag_full().
	 */
	public function expungeDeletedMessages() {
		$this->pingMailbox();
		return imap_expunge($this->mbox);
	}

	// Mark e-mail as seen
	public function markMessageAsRead($mId) {
		$this->pingMailbox();
		$this->setFlag($mId, '\\Seen');
	}

	// Mark e-mail as unseen
	public function markMessageAsUnread($mId) {
		$this->pingMailbox();
		$this->clearFlag($mId, '\\Seen');
	}

	// Mark e-mail as flagged
	public function markMessageAsImportant($mId) {
		$this->pingMailbox();
		$this->setFlag($mId, '\\Flagged');
	}

	/*
	 * bool setFlag ( string $sequence , string $flag )
	 * 
	 * Causes a store to add the specified flag to the flags set for the messages in the specified sequence.
	 * 
	 * sequence
	 *	A sequence of message numbers. You can enumerate desired messages with the X,Y syntax, or retrieve all messages within an interval with the X:Y syntax
	 * flag
	 *	The flags which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by » RFC2060.
	 * 
	 * Returns TRUE on success or FALSE on failure.
	 */
	public function setFlag($sequence, $flag) {
		$this->pingMailbox();
		return imap_setflag_full($this->mbox, $sequence, $flag, ST_UID);
	}

	/*
	 * bool clearFlag ( string $sequence , string $flag )
	 * 
	 * This function causes a store to delete the specified flag to the flags set for the messages in the specified sequence.
	 * 
	 * sequence
	 *  A sequence of message numbers. You can enumerate desired messages with the X,Y syntax, or retrieve all messages within an interval with the X:Y syntax
	 * 
	 * flag
	 *  The flags which you can unset are "\\Seen", "\\Answered", "\\Flagged", "\\Deleted", and "\\Draft" (as defined by » RFC2060)
	 * 
	 * Returns TRUE on success or FALSE on failure.
	 */
	public function clearFlag($sequence, $flag) {
		$this->pingMailbox();
		return imap_clearflag_full($this->mbox, $sequence, $flag, ST_UID);
	}

	/*
	 * string fetchHeader ( int $msg_number )
	 * 
	 * This function causes a fetch of the complete, unfiltered » RFC2822 format header of the specified message.
	 * 
	 * msg_number
	 *	The message number
	 * 
	 * Returns the header of the specified message as a text string.
	 */
	public function fetchHeader($msg_number) {
		$this->pingMailbox();
		/*
		if (!$headers) {
			throw new ImapMailboxException('Message with UID "' . $msg_number . '" not found');
		}
		 */
		return imap_fetchheader($this->mbox, $msg_number, FT_UID);
	}

	/*
	 * array fetchOverview ( string $sequence, bool $asCSVString )
	 * 
	 * This function fetches mail headers for the given sequence and returns an overview of their contents.
	 * 
	 * sequence
	 *  A message sequence description. You can enumerate desired messages with the X,Y syntax, or retrieve all messages within an interval with the X:Y syntax
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
	 */
	public function fetchOverview($sequence) {
		$this->pingMailbox();
		return imap_fetch_overview($this->mbox, $sequence, FT_UID);
	}

	/*
	 * array imap_sort ( rint $criteria , int $reverse )
	 * 
	 * Criteria can be one (and only one) of the following:
	 *  SORTDATE - message Date
	 *  SORTARRIVAL - arrival date (default)
	 *  SORTFROM - mailbox in first From address
	 *  SORTSUBJECT - message subject
	 *  SORTTO - mailbox in first To address
	 *  SORTCC - mailbox in first cc address
	 *  SORTSIZE - size of message in octets
	 * 
	 * reverse
	 *  Set this to 1 for reverse sorting (default)
	 * 
	 * asString
	 *  Boolean value, return array in comma separated value format
	 * 
	 * Returns an array of message numbers sorted by the given parameters.
	 */
	public function sortMessages($criteria = SORTARRIVAL, $reverse = 1, $asString = false) {
		$this->pingMailbox();
		$list = imap_sort($this->mbox, $criteria, $reverse, SE_UID);
		if ($asString) {
			$list = rtrim(implode(',', $list), ',');
		}
		return $list;
	}

	/*
	 * int countMessages ( )
	 * 
	 * Gets the number of messages in the current mailbox.
	 * 
	 * Return the number of messages in the current mailbox, as an integer.
	 */
	public function countMessages() {
		$this->pingMailbox();
		return imap_num_msg($this->mbox);
	}

	public function getMail($mId) {
		$this->pingMailbox();
		$head = imap_rfc822_parse_headers($this->fetchHeader($mId));

		$mail = new IncomingMail();
		$mail->mId = $mId;
		$mail->date = date('Y-m-d H:i:s', isset($head->date) ? strtotime($head->date) : time());
		$mail->subject = $this->decodeMimeStr($head->subject);
		$mail->fromName = isset($head->from[0]->personal) ? $this->decodeMimeStr($head->from[0]->personal) : null;
		$mail->fromAddress = strtolower($head->from[0]->mailbox . '@' . $head->from[0]->host);

		$toStrings = array();
		foreach ($head->to as $to) {
			$toEmail = strtolower($to->mailbox . '@' . $to->host);
			$toName = isset($to->personal) ? $this->decodeMimeStr($to->personal) : null;
			$toStrings[] = $toName ? "$toName <$toEmail>" : $toEmail;
			$mail->to[$toEmail] = $toName;
		}
		$mail->toString = implode(', ', $toStrings);

		if (isset($head->cc)) {
			foreach ($head->cc as $cc) {
				$mail->cc[strtolower($cc->mailbox . '@' . $cc->host)] = isset($cc->personal) ? $this->decodeMimeStr($cc->personal) : null;
			}
		}

		if (isset($head->reply_to)) {
			foreach ($head->reply_to as $replyTo) {
				$mail->replyTo[strtolower($replyTo->mailbox . '@' . $replyTo->host)] = isset($replyTo->personal) ? $this->decodeMimeStr($replyTo->personal) : null;
			}
		}

		// object imap_fetchstructure ( resource $imap_stream , int $msg_number [, int $options = 0 ] )
		// Fetches all the structured information for a given message.
		// msg_number
		//	The message number
		// options
		//	This optional parameter only has a single option, FT_UID, which tells the function to treat the msg_number argument as a UID.
		//
		// Returns an object includes the envelope, internal date, size, flags and body structure along with a similar object for each mime attachment. The structure of the returned objects is as follows:
		// type				Primary body type
		// encoding			Body transfer encoding
		// ifsubtype		TRUE if there is a subtype string
		// subtype			MIME subtype
		// ifdescription	TRUE if there is a description string
		// description		Content description string
		// ifid				TRUE if there is an identification string
		// id				Identification string
		// lines			Number of lines
		// bytes			Number of bytes
		// ifdisposition	TRUE if there is a disposition string
		// disposition		Disposition string
		// ifdparameters	TRUE if the dparameters array exists
		// dparameters		An array of objects where each object has an "attribute" and a "value" property corresponding to the parameters on the Content-disposition MIME header.
		// ifparameters		TRUE if the parameters array exists
		// parameters		An array of objects where each object has an "attribute" and a "value" property.
		// parts			An array of objects identical in structure to the top-level object, each of which corresponds to a MIME body part.
		//
		// Primary body type (may vary with used library)
		// 0	text
		// 1	multipart
		// 2	message
		// 3	application
		// 4	audio
		// 5	image
		// 6	video
		// 7	other
		//
		// Transfer encodings (may vary with used library)
		// 0	7BIT
		// 1	8BIT
		// 2	BINARY
		// 3	BASE64
		// 4	QUOTED-PRINTABLE
		// 5	OTHER
		//
		// See Also
		// imap_fetchbody() - Fetch a particular section of the body of the message
		// imap_bodystruct() - Read the structure of a specified body section of a specific message
		$struct = imap_fetchstructure($this->mbox, $mId, FT_UID);

		if (empty($struct->parts)) {
			$this->initMailPart($mail, $struct, 0);
		} else {
			foreach ($struct->parts as $partNum => $partStruct) {
				$this->initMailPart($mail, $partStruct, $partNum + 1);
			}
		}

		$mail->textHtmlOriginal = $mail->textHtml;

		return $mail;
	}

	public function quoteAttachmentFilename($filename) {
		$replace = array('/\s/' => '_', '/[^0-9a-zA-Z_\.]/' => '', '/_+/' => '_', '/(^_)|(_$)/' => '');

		return preg_replace(array_keys($replace), $replace, $filename);
	}

	public function initMailPart(IncomingMail $mail, $partStruct, $partNum) {
		$data = $partNum ? imap_fetchbody($this->mbox, $mail->mId, $partNum, FT_UID) : imap_body($this->mbox, $mail->mId, FT_UID);

		if ($partStruct->encoding == 1) {
			$data = imap_utf8($data);
		} elseif ($partStruct->encoding == 2) {
			$data = imap_binary($data);
		} elseif ($partStruct->encoding == 3) {
			$data = imap_base64($data);
		} elseif ($partStruct->encoding == 4) {
			$data = imap_qprint($data);
		}

		$params = array();
		if (!empty($partStruct->parameters)) {
			foreach ($partStruct->parameters as $param) {
				$params[strtolower($param->attribute)] = $param->value;
			}
		}
		if (!empty($partStruct->dparameters)) {
			foreach ($partStruct->dparameters as $param) {
				$params[strtolower($param->attribute)] = $param->value;
			}
		}
		if (!empty($params['charset'])) {
			$data = iconv($params['charset'], $this->serverEncoding, $data);
		}

		// attachments
		if ($this->attachmentsDir) {
			$filename = false;
			$attachmentId = $partStruct->ifid ? trim($partStruct->id, " <>") : null;
			if (empty($params['filename']) && empty($params['name']) && $attachmentId) {
				$filename = $attachmentId . '.' . strtolower($partStruct->subtype);
			} elseif (!empty($params['filename']) || !empty($params['name'])) {
				$filename = !empty($params['filename']) ? $params['filename'] : $params['name'];
				$filename = $this->decodeMimeStr($filename);
				$filename = $this->quoteAttachmentFilename($filename);
			}
			if ($filename) {
				if ($this->attachmentsDir) {
					$filepath = rtrim($this->attachmentsDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
					file_put_contents($filepath, $data);
					$mail->attachments[$filename] = $filepath;
				} else {
					$mail->attachments[$filename] = $filename;
				}
				if ($attachmentId) {
					$mail->attachmentsIds[$filename] = $attachmentId;
				}
			}
		}
		if ($partStruct->type == 0 && $data) {
			if (strtolower($partStruct->subtype) == 'plain') {
				$mail->textPlain .= $data;
			} else {
				$mail->textHtml .= $data;
			}
		} elseif ($partStruct->type == 2 && $data) {
			$mail->textPlain .= trim($data);
		}
		if (!empty($partStruct->parts)) {
			foreach ($partStruct->parts as $subpartNum => $subpartStruct) {
				$this->initMailPart($mail, $subpartStruct, $partNum . '.' . ($subpartNum + 1));
			}
		}
	}

	public function decodeMimeStr($string, $charset = 'UTF-8') {
		$newString = '';
		$elements = imap_mime_header_decode($string);
		for ($i = 0; $i < count($elements); $i++) {
			if ($elements[$i]->charset == 'default') {
				$elements[$i]->charset = 'iso-8859-1';
			}
			$newString .= iconv($elements[$i]->charset, $charset, $elements[$i]->text);
		}
		return $newString;
	}

	public function __call($imapFunction, $args) {
		$result = call_user_func_array($imapFunction, $args);
		$errors = imap_errors();
		if ($errors) {
			foreach ($errors as $error) {
				trigger_error($error);
			}
		}
		return $result;
	}

	public function __destruct() {
		$this->disconnect();
	}

/*
 * Un-Implemented IMAP Connection Functions
 */
	// imap_alerts — Returns all IMAP alert messages that have occurred
	// imap_errors — Returns all of the IMAP errors that have occured
	// imap_gc — Clears IMAP cache
	// imap_last_error — Gets the last IMAP error that occurred during this page request
	// imap_timeout — Set or fetch imap timeout

/*
 * Un-Implemented IMAP General Functions
 */
	// imap_mail_compose — Create a MIME message based on given envelope and body sections
	// imap_get_quotaroot — Retrieve the quota settings per user
	// imap_mail — Send an email message
	// imap_thread — Returns a tree of threaded message

/*
 * Un-Implemented IMAP Mailbox Functions
 */
	// imap_append — Append a string message to a specified mailbox
	// imap_createmailbox — Create a new mailbox
	// imap_deletemailbox — Delete a mailbox
	// imap_get_quota — Retrieve the quota level settings, and usage statics per mailbox
	// imap_getacl — Gets the ACL for a given mailbox
	// imap_getmailboxes — Read the list of mailboxes, returning detailed information on each one
	// imap_getsubscribed — List all the subscribed mailboxes
	// imap_list — Read the list of mailboxes
	// imap_listscan — Returns the list of mailboxes that matches the given text
	// imap_lsub — List all the subscribed mailboxes
	// imap_mail_copy — Copy specified messages to a mailbox
	// imap_mail_move — Move specified messages to a mailbox
	// imap_mailboxmsginfo — Get information about the current mailbox
	// imap_num_recent — Gets the number of recent messages in current mailbox
	// imap_renamemailbox — Rename an old mailbox to new mailbox
	// imap_reopen — Reopen IMAP stream to new mailbox
	// imap_set_quota — Sets a quota for a given mailbox
	// imap_setacl — Sets the ACL for a giving mailbox
	// imap_status — Returns status information on a mailbox
	// imap_subscribe — Subscribe to a mailbox
	// imap_unsubscribe — Unsubscribe from a mailbox

/*
 * Un-Implemented IMAP Message Functions
 */
	// imap_bodystruct — Read the structure of a specified body section of a specific message
	// imap_fetchmime — Fetch MIME headers for a particular section of the message
	// imap_headerinfo — Read the header of the message
	// imap_headers — Returns headers for all messages in a mailbox
	// imap_msgno — Gets the message sequence number for the given UID
	// imap_savebody — Save a specific body section to a file
	// imap_uid — This function returns the UID for the given message sequence number

/*
 * Un-Implemented IMAP Encoding Functions
 */
	// imap_8bit — Convert an 8bit string to a quoted-printable string
	// imap_rfc822_parse_adrlist — Parses an address string
	// imap_rfc822_write_address — Returns a properly formatted email address given the mailbox, host, and personal info
	// imap_utf7_decode — Decodes a modified UTF-7 encoded string
	// imap_utf7_encode — Converts ISO-8859-1 string to modified UTF-7 text
}

class IncomingMail {

	public $mId;
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
		if ($this->textHtml) {
			foreach ($this->attachments as $filepath) {
				$filename = basename($filepath);
				if (isset($this->attachmentsIds[$filename])) {
					$this->textHtml = preg_replace('/(<img[^>]*?)src=["\']?ci?d:' . preg_quote($this->attachmentsIds[$filename]) . '["\']?/is', '\\1 src="' . $baseUrl . $filename . '"', $this->textHtml);
				}
			}
		}
	}

	public function fetchMessageHtmlTags($stripTags = array('html', 'body', 'head', 'meta')) {
		if ($this->textHtml) {
			foreach ($stripTags as $tag) {
				$this->textHtml = preg_replace('/<\/?' . $tag . '.*?>/is', '', $this->textHtml);
			}
			$this->textHtml = trim($this->textHtml, " \r\n");
		}
	}

}

class ImapMailboxException extends Exception {
}