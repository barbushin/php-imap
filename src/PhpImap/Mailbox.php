<?php namespace PhpImap;

use stdClass;

/**
 * @see https://github.com/barbushin/php-imap
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class Mailbox {

	protected $imapPath;
	protected $imapLogin;
	protected $imapPassword;
	protected $connectionRetry = 0;
	protected $connectionRetryDelay = 100;
	protected $imapOptions = 0;
	protected $imapRetriesNum = 0;
	protected $imapParams = [];
	protected $serverEncoding;
	protected $attachmentsDir = null;
	protected $expungeOnDisconnect = true;
	protected $timeouts = [];
	private $imapStream;
	protected $folders = [];

	/**
	 * @param string $imapPath
	 * @param string $login
	 * @param string $password
	 * @param string $attachmentsDir
	 * @param string $serverEncoding
	 * @throws Exception
	 */
	public function __construct($imapPath, $login, $password, $attachmentsDir = null, $serverEncoding = 'UTF-8') {
		$this->imapPath = $imapPath;
		$this->imapLogin = $login;
		$this->imapPassword = $password;
		$this->serverEncoding = strtoupper($serverEncoding);
		if($attachmentsDir) {
			if(!is_dir($attachmentsDir)) {
				throw new Exception('Directory "' . $attachmentsDir . '" not found');
			}
			$this->attachmentsDir = rtrim(realpath($attachmentsDir), '\\/');
		}
	}

	public function getServerEncoding() {
		return $this->serverEncoding;
	}

	public function setServerEncoding($serverEncoding) {
		$this->serverEncoding = $serverEncoding;
	}

	/**
	 * @param int $timeout Timeout in seconds
	 * @param array $types One of the following: IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT
	 */
	public function setTimeouts($timeout, $types = [IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT]) {
		$this->timeouts = array_fill_keys($types, $timeout);
	}

	/**
	 * @return string
	 */
	public function getLogin() {
		return $this->imapLogin;
	}

	/**
	 * Set custom connection arguments of imap_open method. See http://php.net/imap_open
	 * @param int $options
	 * @param int $retriesNum
	 * @param array $params
	 */
	public function setConnectionArgs($options = 0, $retriesNum = 0, array $params = null) {
		$this->imapOptions = $options;
		$this->imapRetriesNum = $retriesNum;
		$this->imapParams = $params;
	}

	/**
	 * Set custom folder for attachments in case you want to have tree of folders for each email
	 * i.e. a/1 b/1 c/1 where a,b,c - senders, i.e. john@smith.com
	 * @param string $dir folder where to save attachments
	 *
	 * @return void
	 */
	public function setAttachmentsDir($dir) {
		$this->attachmentsDir = $dir;
	}

	public function setConnectionRetry($maxAttempts) {
		$this->connectionRetry = $maxAttempts;
	}

	public function setConnectionRetryDelay($milliseconds) {
		$this->connectionRetryDelay = $milliseconds;
	}

	/**
	 * Get IMAP mailbox connection stream
	 * @param bool $forceConnection Initialize connection if it's not initialized
	 * @return null|resource
	 */
	public function getImapStream($forceConnection = true) {
		if($forceConnection) {
			if($this->imapStream && (!is_resource($this->imapStream) || !imap_ping($this->imapStream))) {
				$this->disconnect();
				$this->imapStream = null;
			}
			if(!$this->imapStream) {
				$this->imapStream = $this->initImapStreamWithRetry();
			}
		}
		return $this->imapStream;
	}

	/**
	 * Switch mailbox without opening a new connection
	 *
	 * @param string $imapPath
	 * @throws Exception
	 */
	public function switchMailbox($imapPath) {
		if(!$this->folders){
			$this->getListingFolders();
		}
		if(in_array($imapPath,$this->folders)){	
			$this->imapPath = array_search($imapPath,$this->folders);
			$this->imap('reopen', $this->imapPath,false);
		}else{
			throw new Exception("Folder [{$imapPath}] does not exist");
		}
	}


	protected function initImapStreamWithRetry() {
		$retry = $this->connectionRetry;

		do {
			try {
				return $this->initImapStream();
			}
			catch(ConnectionException $exception) {
			}
		}
		while(--$retry > 0 && (!$this->connectionRetryDelay || !usleep($this->connectionRetryDelay * 1000)));

		throw $exception;
	}

	protected function initImapStream() {
		foreach($this->timeouts as $type => $timeout) {
			$this->imap('timeout', [$type, $timeout], false);
		}
		return $this->imap('open', [$this->imapPath, $this->imapLogin, $this->imapPassword, $this->imapOptions, $this->imapRetriesNum, $this->imapParams], false, ConnectionException::class);
	}

	public function disconnect() {
		$imapStream = $this->getImapStream(false);
		if($imapStream && is_resource($imapStream)) {
			$this->imap('close', [$imapStream, $this->expungeOnDisconnect ? CL_EXPUNGE : 0], false, null);
		}
	}

	/**
	 * Sets 'expunge on disconnect' parameter
	 * @param bool $isEnabled
	 */
	public function setExpungeOnDisconnect($isEnabled) {
		$this->expungeOnDisconnect = $isEnabled;
	}

	/**
	 * Get information about the current mailbox.
	 *
	 * Returns the information in an object with following properties:
	 *  Date - current system time formatted according to RFC2822
	 *  Driver - protocol used to access this mailbox: POP3, IMAP, NNTP
	 *  Mailbox - the mailbox name
	 *  Nmsgs - number of mails in the mailbox
	 *  Recent - number of recent mails in the mailbox
	 *
	 * @return stdClass
	 */
	public function checkMailbox() {
		return $this->imap('check');
	}

	/**
	 * Creates a new mailbox
	 * @param $name
	 */
	public function createMailbox($name) {
		$this->imap('createmailbox', $this->imapPath . '.' . $name);
	}

	/**
	 * Delete mailbox
	 * @param $name
	 */
	public function deleteMailbox($name) {
		$this->imap('deletemailbox', $this->imapPath . '.' . $name);
	}

	/**
	 * Rename mailbox
	 * @param $oldName
	 * @param $newName
	 */
	public function renameMailbox($oldName, $newName) {
		$this->imap('renamemailbox', [$this->imapPath . '.' . $oldName, $this->imapPath . '.' . $newName]);
	}

	/**
	 * Gets status information about the given mailbox.
	 *
	 * This function returns an object containing status information.
	 * The object has the following properties: messages, recent, unseen, uidnext, and uidvalidity.
	 *
	 * @return stdClass
	 */
	public function statusMailbox() {
		return $this->imap('status', [$this->imapPath, SA_ALL]);
	}

	/**
	 * Gets listing the folders
	 *
	 * This function returns an object containing listing the folders.
	 * The object has the following properties: messages, recent, unseen, uidnext, and uidvalidity.
	 *
	 * @param string $pattern
	 * @return array listing the folders
	 */
	public function getListingFolders() {
		if(!$this->folders){
			$folders = $this->imap('list', [$this->imapPath,'*']) ?: [];
			$nbps = iconv("cp1251","UTF-8",chr(160));
			foreach($folders as &$folder) {
				$folder = preg_replace("#^{imap\.[^}]+}#iu",'',$folder);				
				$this->folders[$folder]=preg_replace("#{$nbps}#iu",' ',mb_convert_encoding($folder, "UTF-8", "UTF7-IMAP"));
			}
		}
		return array_values($this->folders);
	}

	/**
	 * This function uses imap_search() to perform a search on the mailbox currently opened in the given IMAP stream.
	 * For example, to match all unanswered mails sent by Mom, you'd use: "UNANSWERED FROM mom".
	 *
	 * @param string $criteria See http://php.net/imap_search for a complete list of available criteria
	 * @return array mailsIds (or empty array)
	 */
	public function searchMailbox($criteria = 'ALL') {
		return $this->imap('search', [$criteria, SE_UID, $this->serverEncoding]) ?: [];
	}

	/**
	 * Save mail body.
	 * @param $mailId
	 * @param string $filename
	 */
	public function saveMail($mailId, $filename = 'email.eml') {
		$this->imap('savebody', [$filename, $mailId, "", FT_UID]);
	}

	/**
	 * Marks mails listed in mailId for deletion.
	 * @param $mailId
	 */
	public function deleteMail($mailId) {
		$this->imap('delete', [$mailId . ':' . $mailId, FT_UID]);
	}

	/**
	 * Moves mails listed in mailId into new mailbox
	 * @param $mailId
	 * @param $mailBox
	 */
	public function moveMail($mailId, $mailBox) {
		$this->imap('mail_move', [$mailId, $mailBox, CP_UID]) && $this->expungeDeletedMails();
	}

	/**
	 * Copys mails listed in mailId into new mailbox
	 * @param $mailId
	 * @param $mailBox
	 */
	public function copyMail($mailId, $mailBox) {
		$this->imap('mail_copy', [$mailId, $mailBox, CP_UID]) && $this->expungeDeletedMails();
	}

	/**
	 * Deletes all the mails marked for deletion by imap_delete(), imap_mail_move(), or imap_setflag_full().
	 */
	public function expungeDeletedMails() {
		$this->imap('expunge');
	}

	/**
	 * Add the flag \Seen to a mail.
	 * @param $mailId
	 */
	public function markMailAsRead($mailId) {
		$this->setFlag([$mailId], '\\Seen');
	}

	/**
	 * Remove the flag \Seen from a mail.
	 * @param $mailId
	 */
	public function markMailAsUnread($mailId) {
		$this->clearFlag([$mailId], '\\Seen');
	}

	/**
	 * Add the flag \Flagged to a mail.
	 * @param $mailId
	 */
	public function markMailAsImportant($mailId) {
		$this->setFlag([$mailId], '\\Flagged');
	}

	/**
	 * Add the flag \Seen to a mails.
	 * @param array $mailId
	 */
	public function markMailsAsRead(array $mailId) {
		$this->setFlag($mailId, '\\Seen');
	}

	/**
	 * Remove the flag \Seen from some mails.
	 * @param array $mailId
	 */
	public function markMailsAsUnread(array $mailId) {
		$this->clearFlag($mailId, '\\Seen');
	}

	/**
	 * Add the flag \Flagged to some mails.
	 * @param array $mailId
	 */
	public function markMailsAsImportant(array $mailId) {
		$this->setFlag($mailId, '\\Flagged');
	}

	/**
	 * Causes a store to add the specified flag to the flags set for the mails in the specified sequence.
	 *
	 * @param array $mailsIds
	 * @param string $flag which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060.
	 */
	public function setFlag(array $mailsIds, $flag) {
		$this->imap('setflag_full', [implode(',', $mailsIds), $flag, ST_UID]);
	}

	/**
	 * Cause a store to delete the specified flag to the flags set for the mails in the specified sequence.
	 *
	 * @param array $mailsIds
	 * @param string $flag which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060.
	 */
	public function clearFlag(array $mailsIds, $flag) {
		$this->imap('clearflag_full', [implode(',', $mailsIds), $flag, ST_UID]);
	}

	/**
	 * Fetch mail headers for listed mails ids
	 *
	 * Returns an array of objects describing one mail header each. The object will only define a property if it exists. The possible properties are:
	 *  subject - the mails subject
	 *  from - who sent it
	 *  to - recipient
	 *  date - when was it sent
	 *  message_id - Mail-ID
	 *  references - is a reference to this mail id
	 *  in_reply_to - is a reply to this mail id
	 *  size - size in bytes
	 *  uid - UID the mail has in the mailbox
	 *  msgno - mail sequence number in the mailbox
	 *  recent - this mail is flagged as recent
	 *  flagged - this mail is flagged
	 *  answered - this mail is flagged as answered
	 *  deleted - this mail is flagged for deletion
	 *  seen - this mail is flagged as already read
	 *  draft - this mail is flagged as being a draft
	 *
	 * @param array $mailsIds
	 * @return array
	 */
	public function getMailsInfo(array $mailsIds) {
		$mails = $this->imap('fetch_overview', [implode(',', $mailsIds), FT_UID]);
		if(is_array($mails) && count($mails)) {
			foreach($mails as &$mail) {
				if(isset($mail->subject)) {
					$mail->subject = $this->decodeMimeStr($mail->subject, $this->serverEncoding);
				}
				if(isset($mail->from)) {
					$mail->from = $this->decodeMimeStr($mail->from, $this->serverEncoding);
				}
				if(isset($mail->to)) {
					$mail->to = $this->decodeMimeStr($mail->to, $this->serverEncoding);
				}
			}
		}
		return $mails;
	}

	/**
	 * Get headers for all messages in the defined mailbox,
	 * returns an array of string formatted with header info,
	 * one element per mail message.
	 *
	 * @return array
	 */
	public function getMailboxHeaders() {
		return $this->imap('headers');
	}

	/**
	 * Get information about the current mailbox.
	 *
	 * Returns an object with following properties:
	 *  Date - last change (current datetime)
	 *  Driver - driver
	 *  Mailbox - name of the mailbox
	 *  Nmsgs - number of messages
	 *  Recent - number of recent messages
	 *  Unread - number of unread messages
	 *  Deleted - number of deleted messages
	 *  Size - mailbox size
	 *
	 * @return object Object with info
	 */

	public function getMailboxInfo() {
		return $this->imap('mailboxmsginfo');
	}

	/**
	 * Gets mails ids sorted by some criteria
	 *
	 * Criteria can be one (and only one) of the following constants:
	 *  SORTDATE - mail Date
	 *  SORTARRIVAL - arrival date (default)
	 *  SORTFROM - mailbox in first From address
	 *  SORTSUBJECT - mail subject
	 *  SORTTO - mailbox in first To address
	 *  SORTCC - mailbox in first cc address
	 *  SORTSIZE - size of mail in octets
	 *
	 * @param int $criteria
	 * @param bool $reverse
	 * @return array Mails ids
	 */
	public function sortMails($criteria = SORTARRIVAL, $reverse = true) {
		return $this->imap('sort', [$criteria, $reverse, SE_UID]);
	}

	/**
	 * Get mails count in mail box
	 * @return int
	 */
	public function countMails() {
		return $this->imap('num_msg');
	}

	/**
	 * Retrieve the quota settings per user
	 * @return array
	 */
	protected function getQuota() {
		return $this->imap('get_quotaroot', 'INBOX');
	}

	/**
	 * Return quota limit in KB
	 * @return int
	 */
	public function getQuotaLimit() {
		$quota = $this->getQuota();
		return isset($quota['STORAGE']['limit']) ? $quota['STORAGE']['limit'] : 0;
	}

	/**
	 * Return quota usage in KB
	 * @return int FALSE in the case of call failure
	 */
	public function getQuotaUsage() {
		$quota = $this->getQuota();
		return isset($quota['STORAGE']['usage']) ? $quota['STORAGE']['usage'] : 0;
	}

	/**
	 * Get raw mail data
	 *
	 * @param $msgId
	 * @param bool $markAsSeen
	 * @return mixed
	 */
	public function getRawMail($msgId, $markAsSeen = true) {
		$options = FT_UID;
		if(!$markAsSeen) {
			$options |= FT_PEEK;
		}

		return $this->imap('fetchbody', [$msgId, '', $options]);
	}

	/**
	 * Get mail header
	 *
	 * @param $mailId
	 * @return IncomingMailHeader
	 */
	public function getMailHeader($mailId) {
		$headersRaw = $this->imap('fetchheader', [$mailId, FT_UID]);
		$head = imap_rfc822_parse_headers($headersRaw);

		$header = new IncomingMailHeader();
		$header->headersRaw = $headersRaw;
		$header->headers = $head;
		$header->id = $mailId;
		$header->date = date('Y-m-d H:i:s', isset($head->date) ? strtotime(preg_replace('/\(.*?\)/', '', $head->date)) : time());
		$header->subject = isset($head->subject) ? $this->decodeMimeStr($head->subject, $this->serverEncoding) : null;
		if(isset($head->from)) {
			$header->fromName = isset($head->from[0]->personal) ? $this->decodeMimeStr($head->from[0]->personal, $this->serverEncoding) : null;
			$header->fromAddress = strtolower($head->from[0]->mailbox . '@' . $head->from[0]->host);
		}
		elseif(preg_match("/smtp.mailfrom=[-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}/", $headersRaw, $matches)) {
			$header->fromAddress = substr($matches[0], 14);
		}
		if(isset($head->to)) {
			$toStrings = [];
			foreach($head->to as $to) {
				if(!empty($to->mailbox) && !empty($to->host)) {
					$toEmail = strtolower($to->mailbox . '@' . $to->host);
					$toName = isset($to->personal) ? $this->decodeMimeStr($to->personal, $this->serverEncoding) : null;
					$toStrings[] = $toName ? "$toName <$toEmail>" : $toEmail;
					$header->to[$toEmail] = $toName;
				}
			}
			$header->toString = implode(', ', $toStrings);
		}

		if(isset($head->cc)) {
			foreach($head->cc as $cc) {
				if(!empty($cc->mailbox) && !empty($cc->host)) {
					$header->cc[strtolower($cc->mailbox . '@' . $cc->host)] = isset($cc->personal) ? $this->decodeMimeStr($cc->personal, $this->serverEncoding) : null;
				}
			}
		}

		if(isset($head->bcc)) {
			foreach($head->bcc as $bcc) {
				if(!empty($bcc->mailbox) && !empty($bcc->host)) {
					$header->bcc[strtolower($bcc->mailbox . '@' . $bcc->host)] = isset($bcc->personal) ? $this->decodeMimeStr($bcc->personal, $this->serverEncoding) : null;
				}
			}
		}

		if(isset($head->reply_to)) {
			foreach($head->reply_to as $replyTo) {
				$header->replyTo[strtolower($replyTo->mailbox . '@' . $replyTo->host)] = isset($replyTo->personal) ? $this->decodeMimeStr($replyTo->personal, $this->serverEncoding) : null;
			}
		}

		if(isset($head->message_id)) {
			$header->messageId = $head->message_id;
		}

		return $header;
	}

	/**
	 * Get mail data
	 *
	 * @param $mailId
	 * @param bool $markAsSeen
	 * @return IncomingMail
	 */
	public function getMail($mailId, $markAsSeen = true) {
		$mail = new IncomingMail();
		$mail->setHeader($this->getMailHeader($mailId));

		$mailStructure = $this->imap('fetchstructure', [$mailId, FT_UID]);

		if(empty($mailStructure->parts)) {
			$this->initMailPart($mail, $mailStructure, 0, $markAsSeen);
		}
		else {
			foreach($mailStructure->parts as $partNum => $partStructure) {
				$this->initMailPart($mail, $partStructure, $partNum + 1, $markAsSeen);
			}
		}

		return $mail;
	}

	protected function initMailPart(IncomingMail $mail, $partStructure, $partNum, $markAsSeen = true) {
		$options = FT_UID;
		if(!$markAsSeen) {
			$options |= FT_PEEK;
		}

		if($partNum) { // don't use ternary operator to optimize memory usage / parsing speed (see http://fabien.potencier.org/the-php-ternary-operator-fast-or-not.html)
			$data = $this->imap('fetchbody', [$mail->id, $partNum, $options]);
		}
		else {
			$data = $this->imap('body', [$mail->id, $options]);
		}

		if($partStructure->encoding == 1) {
			$data = imap_utf8($data);
		}
		elseif($partStructure->encoding == 2) {
			$data = imap_binary($data);
		}
		elseif($partStructure->encoding == 3) {
			$data = preg_replace('~[^a-zA-Z0-9+=/]+~s', '', $data); // https://github.com/barbushin/php-imap/issues/88
			$data = imap_base64($data);
		}
		elseif($partStructure->encoding == 4) {
			$data = quoted_printable_decode($data);
		}

		$params = [];
		if(!empty($partStructure->parameters)) {
			foreach($partStructure->parameters as $param) {
				$params[strtolower($param->attribute)] = $this->decodeMimeStr($param->value);
			}
		}
		if(!empty($partStructure->dparameters)) {
			foreach($partStructure->dparameters as $param) {
				$paramName = strtolower(preg_match('~^(.*?)\*~', $param->attribute, $matches) ? $matches[1] : $param->attribute);
				if(isset($params[$paramName])) {
					$params[$paramName] .= $param->value;
				}
				else {
					$params[$paramName] = $param->value;
				}
			}
		}

		$isAttachment = $partStructure->ifid || isset($params['filename']) || isset($params['name']);

		// ignore contentId on body when mail isn't multipart (https://github.com/barbushin/php-imap/issues/71)
		if(!$partNum && TYPETEXT === $partStructure->type) {
			$isAttachment = false;
		}

		if($isAttachment) {
			$attachmentId = mt_rand() . mt_rand();

			if(empty($params['filename']) && empty($params['name'])) {
				$fileName = $attachmentId . '.' . strtolower($partStructure->subtype);
			}
			else {
				$fileName = !empty($params['filename']) ? $params['filename'] : $params['name'];
				$fileName = $this->decodeMimeStr($fileName, $this->serverEncoding);
				$fileName = $this->decodeRFC2231($fileName, $this->serverEncoding);
			}

			$attachment = new IncomingMailAttachment();
			$attachment->id = $attachmentId;
			$attachment->contentId = $partStructure->ifid ? trim($partStructure->id, " <>") : null;
			$attachment->name = $fileName;
			$attachment->disposition = (isset($partStructure->disposition) ? $partStructure->disposition : null);
			if($this->attachmentsDir) {
				$replace = [
					'/\s/' => '_',
					'/[^0-9a-zа-яіїє_\.]/iu' => '',
					'/_+/' => '_',
					'/(^_)|(_$)/' => '',
				];
				$fileSysName = preg_replace('~[\\\\/]~', '', $mail->id . '_' . $attachmentId . '_' . preg_replace(array_keys($replace), $replace, $fileName));
				$attachment->filePath = $this->attachmentsDir . DIRECTORY_SEPARATOR . $fileSysName;

				if(strlen($attachment->filePath) > 255) {
					$ext = pathinfo($attachment->filePath, PATHINFO_EXTENSION);
					$attachment->filePath = substr($attachment->filePath, 0, 255 - 1 - strlen($ext)) . "." . $ext;
				}

				file_put_contents($attachment->filePath, $data);
			}
			$mail->addAttachment($attachment);
		}
		else {
			if(!empty($params['charset'])) {
				$data = $this->convertStringEncoding($data, $params['charset'], $this->serverEncoding);
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
		}
		if(!empty($partStructure->parts)) {
			foreach($partStructure->parts as $subPartNum => $subPartStructure) {
				if($partStructure->type == 2 && $partStructure->subtype == 'RFC822' && (!isset($partStructure->disposition) || $partStructure->disposition !== "attachment")) {
					$this->initMailPart($mail, $subPartStructure, $partNum, $markAsSeen);
				}
				else {
					$this->initMailPart($mail, $subPartStructure, $partNum . '.' . ($subPartNum + 1), $markAsSeen);
				}
			}
		}
	}

	protected function decodeMimeStr($string, $toCharset = 'utf-8') {
		$newString = '';
		foreach(imap_mime_header_decode($string) as $element) {
			if(isset($element->text)) {
				$fromCharset = !isset($element->charset) || $element->charset == 'default' ? 'iso-8859-1' : $element->charset;
				$newString .= $this->convertStringEncoding($element->text, $fromCharset, $toCharset);
			}
		}
		return $newString;
	}

	function isUrlEncoded($string) {
		$hasInvalidChars = preg_match('#[^%a-zA-Z0-9\-_\.\+]#', $string);
		$hasEscapedChars = preg_match('#%[a-zA-Z0-9]{2}#', $string);
		return !$hasInvalidChars && $hasEscapedChars;
	}

	protected function decodeRFC2231($string, $charset = 'utf-8') {
		if(preg_match("/^(.*?)'.*?'(.*?)$/", $string, $matches)) {
			$encoding = $matches[1];
			$data = $matches[2];
			if($this->isUrlEncoded($data)) {
				$string = $this->convertStringEncoding(urldecode($data), $encoding, $charset);
			}
		}
		return $string;
	}

	/**
	 * Converts a string from one encoding to another.
	 * @param string $string
	 * @param string $fromEncoding
	 * @param string $toEncoding
	 * @return string Converted string if conversion was successful, or the original string if not
	 * @throws Exception
	 */
	protected function convertStringEncoding($string, $fromEncoding, $toEncoding) {
		
		$Enc = array(
			'ascii'=>'us-ascii',
			'us-ascii'=>'us-ascii',
			'ansi_x3.4-1968'=>'us-ascii',
			'646'=>'us-ascii',
			'iso-8859-1'=>'ISO-8859-1',
			'iso-8859-2'=>'ISO-8859-2',
			'iso-8859-3'=>'ISO-8859-3',
			'iso-8859-4'=>'ISO-8859-4',
			'iso-8859-5'=>'ISO-8859-5',
			'iso-8859-6'=>'ISO-8859-6',
			'iso-8859-6-i'=>'ISO-8859-6-I',
			'iso-8859-6-e'=>'ISO-8859-6-E',
			'iso-8859-7'=>'ISO-8859-7',
			'iso-8859-8'=>'ISO-8859-8',
			'iso-8859-8-i'=>'ISO-8859-8-I',
			'iso-8859-8-e'=>'ISO-8859-8-E',
			'iso-8859-9'=>'ISO-8859-9',
			'iso-8859-10'=>'ISO-8859-10',
			'iso-8859-11'=>'ISO-8859-11',
			'iso-8859-13'=>'ISO-8859-13',
			'iso-8859-14'=>'ISO-8859-14',
			'iso-8859-15'=>'ISO-8859-15',
			'iso-8859-16'=>'ISO-8859-16',
			'iso-ir-111'=>'ISO-IR-111',
			'iso-2022-cn'=>'ISO-2022-CN',
			'iso-2022-cn-ext'=>'ISO-2022-CN',
			'iso-2022-kr'=>'ISO-2022-KR',
			'iso-2022-jp'=>'ISO-2022-JP',
			'utf-16be'=>'UTF-16BE',
			'utf-16le'=>'UTF-16LE',
			'utf-16'=>'UTF-16',
			'windows-1250'=>'windows-1250',
			'windows-1251'=>'windows-1251',
			'windows-1252'=>'windows-1252',
			'windows-1253'=>'windows-1253',
			'windows-1254'=>'windows-1254',
			'windows-1255'=>'windows-1255',
			'windows-1256'=>'windows-1256',
			'windows-1257'=>'windows-1257',
			'windows-1258'=>'windows-1258',
			'ibm866'=>'IBM866',
			'ibm850'=>'IBM850',
			'ibm852'=>'IBM852',
			'ibm855'=>'IBM855',
			'ibm857'=>'IBM857',
			'ibm862'=>'IBM862',
			'ibm864'=>'IBM864',
			'utf-8'=>'UTF-8',
			'utf-7'=>'UTF-7',
			'shift_jis'=>'Shift_JIS',
			'big5'=>'Big5',
			'euc-jp'=>'EUC-JP',
			'euc-kr'=>'EUC-KR',
			'gb2312'=>'GB2312',
			'gb18030'=>'gb18030',
			'viscii'=>'VISCII',
			'koi8-r'=>'KOI8-R',
			'koi8_r'=>'KOI8-R',
			'cskoi8r'=>'KOI8-R',
			'koi'=>'KOI8-R',
			'koi8'=>'KOI8-R',
			'koi8-u'=>'KOI8-U',
			'tis-620'=>'TIS-620',
			't.61-8bit'=>'T.61-8bit',
			'hz-gb-2312'=>'HZ-GB-2312',
			'big5-hkscs'=>'Big5-HKSCS',
			'gbk'=>'gbk',
			'cns11643'=>'x-euc-tw',
			'x-imap4-modified-utf7'=>'x-imap4-modified-utf7',
			'x-euc-tw'=>'x-euc-tw',
			'x-mac-ce'=>'x-mac-ce',
			'x-mac-turkish'=>'x-mac-turkish',
			'x-mac-greek'=>'x-mac-greek',
			'x-mac-icelandic'=>'x-mac-icelandic',
			'x-mac-croatian'=>'x-mac-croatian',
			'x-mac-romanian'=>'x-mac-romanian',
			'x-mac-cyrillic'=>'x-mac-cyrillic',
			'x-mac-ukrainian'=>'x-mac-cyrillic',
			'x-mac-hebrew'=>'x-mac-hebrew',
			'x-mac-arabic'=>'x-mac-arabic',
			'x-mac-farsi'=>'x-mac-farsi',
			'x-mac-devanagari'=>'x-mac-devanagari',
			'x-mac-gujarati'=>'x-mac-gujarati',
			'x-mac-gurmukhi'=>'x-mac-gurmukhi',
			'armscii-8'=>'armscii-8',
			'x-viet-tcvn5712'=>'x-viet-tcvn5712',
			'x-viet-vps'=>'x-viet-vps',
			'iso-10646-ucs-2'=>'UTF-16BE',
			'x-iso-10646-ucs-2-be'=>'UTF-16BE',
			'x-iso-10646-ucs-2-le'=>'UTF-16LE',
			'x-user-defined'=>'x-user-defined',
			'x-johab'=>'x-johab',
			'latin1'=>'ISO-8859-1',
			'iso_8859-1'=>'ISO-8859-1',
			'iso8859-1'=>'ISO-8859-1',
			'iso8859-2'=>'ISO-8859-2',
			'iso8859-3'=>'ISO-8859-3',
			'iso8859-4'=>'ISO-8859-4',
			'iso8859-5'=>'ISO-8859-5',
			'iso8859-6'=>'ISO-8859-6',
			'iso8859-7'=>'ISO-8859-7',
			'iso8859-8'=>'ISO-8859-8',
			'iso8859-9'=>'ISO-8859-9',
			'iso8859-10'=>'ISO-8859-10',
			'iso8859-11'=>'ISO-8859-11',
			'iso8859-13'=>'ISO-8859-13',
			'iso8859-14'=>'ISO-8859-14',
			'iso8859-15'=>'ISO-8859-15',
			'iso_8859-1:1987'=>'ISO-8859-1',
			'iso-ir-100'=>'ISO-8859-1',
			'l1'=>'ISO-8859-1',
			'ibm819'=>'ISO-8859-1',
			'cp819'=>'ISO-8859-1',
			'csisolatin1'=>'ISO-8859-1',
			'latin2'=>'ISO-8859-2',
			'iso_8859-2'=>'ISO-8859-2',
			'iso_8859-2:1987'=>'ISO-8859-2',
			'iso-ir-101'=>'ISO-8859-2',
			'l2'=>'ISO-8859-2',
			'csisolatin2'=>'ISO-8859-2',
			'latin3'=>'ISO-8859-3',
			'iso_8859-3'=>'ISO-8859-3',
			'iso_8859-3:1988'=>'ISO-8859-3',
			'iso-ir-109'=>'ISO-8859-3',
			'l3'=>'ISO-8859-3',
			'csisolatin3'=>'ISO-8859-3',
			'latin4'=>'ISO-8859-4',
			'iso_8859-4'=>'ISO-8859-4',
			'iso_8859-4:1988'=>'ISO-8859-4',
			'iso-ir-110'=>'ISO-8859-4',
			'l4'=>'ISO-8859-4',
			'csisolatin4'=>'ISO-8859-4',
			'cyrillic'=>'ISO-8859-5',
			'iso_8859-5'=>'ISO-8859-5',
			'iso_8859-5:1988'=>'ISO-8859-5',
			'iso-ir-144'=>'ISO-8859-5',
			'csisolatincyrillic'=>'ISO-8859-5',
			'arabic'=>'ISO-8859-6',
			'iso_8859-6'=>'ISO-8859-6',
			'iso_8859-6:1987'=>'ISO-8859-6',
			'iso-ir-127'=>'ISO-8859-6',
			'ecma-114'=>'ISO-8859-6',
			'asmo-708'=>'ISO-8859-6',
			'csisolatinarabic'=>'ISO-8859-6',
			'csiso88596i'=>'ISO-8859-6-I',
			'csiso88596e'=>'ISO-8859-6-E',
			'greek'=>'ISO-8859-7',
			'greek8'=>'ISO-8859-7',
			'sun_eu_greek'=>'ISO-8859-7',
			'iso_8859-7'=>'ISO-8859-7',
			'iso_8859-7:1987'=>'ISO-8859-7',
			'iso-ir-126'=>'ISO-8859-7',
			'elot_928'=>'ISO-8859-7',
			'ecma-118'=>'ISO-8859-7',
			'csisolatingreek'=>'ISO-8859-7',
			'hebrew'=>'ISO-8859-8',
			'iso_8859-8'=>'ISO-8859-8',
			'visual'=>'ISO-8859-8',
			'iso_8859-8:1988'=>'ISO-8859-8',
			'iso-ir-138'=>'ISO-8859-8',
			'csisolatinhebrew'=>'ISO-8859-8',
			'csiso88598i'=>'ISO-8859-8-I',
			'iso-8859-8i'=>'ISO-8859-8-I',
			'logical'=>'ISO-8859-8-I',
			'csiso88598e'=>'ISO-8859-8-E',
			'latin5'=>'ISO-8859-9',
			'iso_8859-9'=>'ISO-8859-9',
			'iso_8859-9:1989'=>'ISO-8859-9',
			'iso-ir-148'=>'ISO-8859-9',
			'l5'=>'ISO-8859-9',
			'csisolatin5'=>'ISO-8859-9',
			'unicode-1-1-utf-8'=>'UTF-8',
			'utf8'=>'UTF-8',
			'x-sjis'=>'Shift_JIS',
			'shift-jis'=>'Shift_JIS',
			'ms_kanji'=>'Shift_JIS',
			'csshiftjis'=>'Shift_JIS',
			'windows-31j'=>'Shift_JIS',
			'cp932'=>'Shift_JIS',
			'sjis'=>'Shift_JIS',
			'cseucpkdfmtjapanese'=>'EUC-JP',
			'x-euc-jp'=>'EUC-JP',
			'csiso2022jp'=>'ISO-2022-JP',
			'iso-2022-jp-2'=>'ISO-2022-JP',
			'csiso2022jp2'=>'ISO-2022-JP',
			'csbig5'=>'Big5',
			'cn-big5'=>'Big5',
			'x-x-big5'=>'Big5',
			'zh_tw-big5'=>'Big5',
			'cseuckr'=>'EUC-KR',
			'ks_c_5601-1987'=>'EUC-KR',
			'iso-ir-149'=>'EUC-KR',
			'ks_c_5601-1989'=>'EUC-KR',
			'ksc_5601'=>'EUC-KR',
			'ksc5601'=>'EUC-KR',
			'korean'=>'EUC-KR',
			'csksc56011987'=>'EUC-KR',
			'5601'=>'EUC-KR',
			'windows-949'=>'EUC-KR',
			'gb_2312-80'=>'GB2312',
			'iso-ir-58'=>'GB2312',
			'chinese'=>'GB2312',
			'csiso58gb231280'=>'GB2312',
			'csgb2312'=>'GB2312',
			'zh_cn.euc'=>'GB2312',
			'gb_2312'=>'GB2312',
			'x-cp1250'=>'windows-1250',
			'x-cp1251'=>'windows-1251',
			'x-cp1252'=>'windows-1252',
			'x-cp1253'=>'windows-1253',
			'x-cp1254'=>'windows-1254',
			'x-cp1255'=>'windows-1255',
			'x-cp1256'=>'windows-1256',
			'x-cp1257'=>'windows-1257',
			'x-cp1258'=>'windows-1258',
			'windows-874'=>'windows-874',
			'ibm874'=>'windows-874',
			'dos-874'=>'windows-874',
			'macintosh'=>'macintosh',
			'x-mac-roman'=>'macintosh',
			'mac'=>'macintosh',
			'csmacintosh'=>'macintosh',
			'cp866'=>'IBM866',
			'cp-866'=>'IBM866',
			'866'=>'IBM866',
			'csibm866'=>'IBM866',
			'cp850'=>'IBM850',
			'850'=>'IBM850',
			'csibm850'=>'IBM850',
			'cp852'=>'IBM852',
			'852'=>'IBM852',
			'csibm852'=>'IBM852',
			'cp855'=>'IBM855',
			'855'=>'IBM855',
			'csibm855'=>'IBM855',
			'cp857'=>'IBM857',
			'857'=>'IBM857',
			'csibm857'=>'IBM857',
			'cp862'=>'IBM862',
			'862'=>'IBM862',
			'csibm862'=>'IBM862',
			'cp864'=>'IBM864',
			'864'=>'IBM864',
			'csibm864'=>'IBM864',
			'ibm-864'=>'IBM864',
			't.61'=>'T.61-8bit',
			'iso-ir-103'=>'T.61-8bit',
			'csiso103t618bit'=>'T.61-8bit',
			'x-unicode-2-0-utf-7'=>'UTF-7',
			'unicode-2-0-utf-7'=>'UTF-7',
			'unicode-1-1-utf-7'=>'UTF-7',
			'csunicode11utf7'=>'UTF-7',
			'csunicode'=>'UTF-16BE',
			'csunicode11'=>'UTF-16BE',
			'iso-10646-ucs-basic'=>'UTF-16BE',
			'csunicodeascii'=>'UTF-16BE',
			'iso-10646-unicode-latin1'=>'UTF-16BE',
			'csunicodelatin1'=>'UTF-16BE',
			'iso-10646'=>'UTF-16BE',
			'iso-10646-j-1'=>'UTF-16BE',
			'latin6'=>'ISO-8859-10',
			'iso-ir-157'=>'ISO-8859-10',
			'l6'=>'ISO-8859-10',
			'csisolatin6'=>'ISO-8859-10',
			'iso_8859-15'=>'ISO-8859-15',
			'csisolatin9'=>'ISO-8859-15',
			'l9'=>'ISO-8859-15',
			'ecma-cyrillic'=>'ISO-IR-111',
			'csiso111ecmacyrillic'=>'ISO-IR-111',
			'csiso2022kr'=>'ISO-2022-KR',
			'csviscii'=>'VISCII',
			'zh_tw-euc'=>'x-euc-tw',
			'iso88591'=>'ISO-8859-1',
			'iso88592'=>'ISO-8859-2',
			'iso88593'=>'ISO-8859-3',
			'iso88594'=>'ISO-8859-4',
			'iso88595'=>'ISO-8859-5',
			'iso88596'=>'ISO-8859-6',
			'iso88597'=>'ISO-8859-7',
			'iso88598'=>'ISO-8859-8',
			'iso88599'=>'ISO-8859-9',
			'iso885910'=>'ISO-8859-10',
			'iso885911'=>'ISO-8859-11',
			'iso885912'=>'ISO-8859-12',
			'iso885913'=>'ISO-8859-13',
			'iso885914'=>'ISO-8859-14',
			'iso885915'=>'ISO-8859-15',
			'tis620'=>'TIS-620',
			'cp1250'=>'windows-1250',
			'cp1251'=>'windows-1251',
			'cp1252'=>'windows-1252',
			'cp1253'=>'windows-1253',
			'cp1254'=>'windows-1254',
			'cp1255'=>'windows-1255',
			'cp1256'=>'windows-1256',
			'cp1257'=>'windows-1257',
			'cp1258'=>'windows-1258',
			'x-gbk'=>'gbk',
			'windows-936'=>'gbk',
			'ansi-1251'=>'windows-1251',
		);
		if(isset($Enc[$fromEncoding]))
			$fromEncoding = $Enc[$fromEncoding];
		
		if(!$string || $fromEncoding == $toEncoding) {
			return $string;
		}
		$convertedString = function_exists('iconv') ? @iconv($fromEncoding, $toEncoding . '//IGNORE', $string) : null;
		if(!$convertedString && extension_loaded('mbstring')) {
			$convertedString = @mb_convert_encoding($string, $toEncoding, $fromEncoding);
		}
		if(!$convertedString) {
			throw new Exception('Mime string encoding conversion failed');
		}
		return $convertedString;
	}

	public function __destruct() {
		$this->disconnect();
	}

	/**
	 * Gets imappath
	 * @return string
	 */
	public function getImapPath() {
		return $this->imapPath;
	}

	/**
	 * Get message in MBOX format
	 * @param $mailId
	 * @return string
	 */
	public function getMailMboxFormat($mailId) {
		return imap_fetchheader($this->getImapStream(), $mailId, FT_UID && FT_PREFETCHTEXT) . imap_body($this->getImapStream(), $mailId, FT_UID);
	}

	/**
	 * Get folders list
	 * @param string $search
	 * @return array
	 */
	public function getMailboxes($search = "*") {
		$arr = [];
		if($t = imap_getmailboxes($this->getImapStream(), $this->imapPath, $search)) {
			foreach($t as $item) {
				$arr[] = [
					"fullpath" => $item->name,
					"attributes" => $item->attributes,
					"delimiter" => $item->delimiter,
					"shortpath" => substr($item->name, strpos($item->name, '}') + 1),
				];
			}
		}
		return $arr;
	}

	/**
	 * Call IMAP extension function call wrapped with utf7 args conversion & errors handling
	 *
	 * @param $methodShortName
	 * @param array|string $args
	 * @param bool $prependConnectionAsFirstArg
	 * @param string|null $throwExceptionClass
	 * @return mixed
	 * @throws Exception
	 */
	public function imap($methodShortName, $args = [], $prependConnectionAsFirstArg = true, $throwExceptionClass = Exception::class) {
		
		if(!is_array($args)) {
			$args = [$args];
		}
		foreach($args as &$arg) {
			if(is_string($arg)) {
				$arg = imap_utf7_encode($arg);
			}
		}
		if($prependConnectionAsFirstArg) {
			array_unshift($args, $this->getImapStream());
		}

		imap_errors(); // flush errors
		$result = @call_user_func_array("imap_$methodShortName", $args);
		
		if(!$result) {
			$errors = imap_errors();
			if($errors) {
				if($throwExceptionClass) {
					throw new $throwExceptionClass("IMAP method imap_$methodShortName() failed with error: " . implode('. ', $errors));
				}
				else {
					return false;
				}
			}
		}

		return $result;
	}
}

class Exception extends \Exception {

}

class ConnectionException extends Exception {

}
