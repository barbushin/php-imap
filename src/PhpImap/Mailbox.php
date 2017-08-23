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
	protected $imapOptions = 0;
	protected $imapRetriesNum = 0;
	protected $imapParams = array();
	protected $serverEncoding;
	protected $attachmentsDir = null;
	protected $expungeOnDisconnect = true;
	private $imapStream;

	/**
         * @param string $imapPath
         * @param string $login
         * @param string $password
         * @param string $attachmentsDir
         * @param string $serverEncoding
         * @throws Exception
         */
	public function __construct($imapPath, $login, $password, $attachmentsDir = null, $serverEncoding = 'UTF-8') {
		$this->setImapPath($imapPath);
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
				$this->imapStream = $this->initImapStream();
			}
		}
		return $this->imapStream;
	}

	/**
	 * Switch mailbox without opening a new connection
	 * 
	 * @param string $imapPath
	 */
	public function switchMailbox($imapPath = '') {
		$this->setImapPath($imapPath);
		$imapStream = @imap_reopen($this->getImapStream(), $this->imapPath);
		if(!$imapStream) {
			throw new Exception("Couldn't switch  mailbox: " . imap_last_error());
		}
	}

	protected function initImapStream() {
		$imapStream = @imap_open($this->imapPath, $this->imapLogin, $this->imapPassword, $this->imapOptions, $this->imapRetriesNum, $this->imapParams);
		if(!$imapStream) {
			$lastError = imap_last_error();
			imap_errors();
			throw new Exception('Connection error: ' . $lastError);
		}
		return $imapStream;
	}

	protected function disconnect() {
		$imapStream = $this->getImapStream(false);
		if($imapStream && is_resource($imapStream)) {
			imap_close($imapStream, $this->expungeOnDisconnect ? CL_EXPUNGE : 0);
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
		return imap_check($this->getImapStream());
	}

	/**
	 * Creates a new mailbox specified by mailbox.
	 *
	 * @return bool
	 */

	public function createMailbox() {
		return imap_createmailbox($this->getImapStream(), imap_utf7_encode($this->imapPath));
	}

	/**
	 * Gets status information about the given mailbox.
	 *
	 * This function returns an object containing status information.
	 * The object has the following properties: messages, recent, unseen, uidnext, and uidvalidity.
	 *
	 * @return stdClass if the box doesn't exist
	 */

	public function statusMailbox() {
		return imap_status($this->getImapStream(), $this->imapPath, SA_ALL);
	}


	/**
	 * Gets listing the folders
	 *
	 * This function returns an object containing listing the folders.
	 * The object has the following properties: messages, recent, unseen, uidnext, and uidvalidity.
	 *
	 * @return array listing the folders
	 */

	public function getListingFolders() {
		$folders = imap_list($this->getImapStream(), $this->imapPath, "*");
		foreach ($folders as $key => $folder)
		{
			if (function_exists('mb_convert_encoding')) {
				$folder = str_replace($this->imapPath, "", mb_convert_encoding($folder, "UTF-8", "UTF7-IMAP"));
			} else {
				$folder = str_replace($this->imapPath, "", imap_utf7_decode($folder));
			}
			$folders[$key] = $folder;
		}
		return $folders;
	}


	/**
	 * This function uses imap_search() to perform a search on the mailbox currently opened in the given IMAP stream.
	 * For example, to match all unanswered mails sent by Mom, you'd use: "UNANSWERED FROM mom".
	 *
	 * @param string $criteria See http://php.net/imap_search for a complete list of available criteria
	 * @return array mailsIds (or empty array)
	 */
	public function searchMailbox($criteria = 'ALL') {
		$mailsIds = imap_search($this->getImapStream(), $criteria, SE_UID, $this->serverEncoding);
		return $mailsIds ? $mailsIds : array();
	}

	/**
	 * Save mail body.
	 * @return bool
	 */
	public function saveMail($mailId, $filename = 'email.eml') {
		return imap_savebody($this->getImapStream(), $filename, $mailId, "", FT_UID);
	}

	/**
	 * Marks mails listed in mailId for deletion.
	 * @return bool
	 */
	public function deleteMail($mailId) {
		return imap_delete($this->getImapStream(), $mailId, FT_UID);
	}

	/**
	 * Moves mails listed in mailId into new mailbox
	 * @return bool
	 */
	public function moveMail($mailId, $mailBox) {
		return imap_mail_move($this->getImapStream(), $mailId, $mailBox, CP_UID) && $this->expungeDeletedMails();
	}

	/**
	 * Copys mails listed in mailId into new mailbox
	 * @return bool
	 */
	public function copyMail($mailId, $mailBox) {
		return imap_mail_copy($this->getImapStream(), $mailId, $mailBox, CP_UID) && $this->expungeDeletedMails();
	}

	/**
	 * Deletes all the mails marked for deletion by imap_delete(), imap_mail_move(), or imap_setflag_full().
	 * @return bool
	 */
	public function expungeDeletedMails() {
		return imap_expunge($this->getImapStream());
	}

	/**
	 * Add the flag \Seen to a mail.
	 * @return bool
	 */
	public function markMailAsRead($mailId) {
		return $this->setFlag(array($mailId), '\\Seen');
	}

	/**
	 * Remove the flag \Seen from a mail.
	 * @return bool
	 */
	public function markMailAsUnread($mailId) {
		return $this->clearFlag(array($mailId), '\\Seen');
	}

	/**
	 * Add the flag \Flagged to a mail.
	 * @return bool
	 */
	public function markMailAsImportant($mailId) {
		return $this->setFlag(array($mailId), '\\Flagged');
	}

	/**
	 * Add the flag \Seen to a mails.
	 * @return bool
	 */
	public function markMailsAsRead(array $mailId) {
		return $this->setFlag($mailId, '\\Seen');
	}

	/**
	 * Remove the flag \Seen from some mails.
	 * @return bool
	 */
	public function markMailsAsUnread(array $mailId) {
		return $this->clearFlag($mailId, '\\Seen');
	}

	/**
	 * Add the flag \Flagged to some mails.
	 * @return bool
	 */
	public function markMailsAsImportant(array $mailId) {
		return $this->setFlag($mailId, '\\Flagged');
	}

	/**
	 * Causes a store to add the specified flag to the flags set for the mails in the specified sequence.
	 *
	 * @param array $mailsIds
	 * @param string $flag which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060.
	 * @return bool
	 */
	public function setFlag(array $mailsIds, $flag) {
		return imap_setflag_full($this->getImapStream(), implode(',', $mailsIds), $flag, ST_UID);
	}

	/**
	 * Cause a store to delete the specified flag to the flags set for the mails in the specified sequence.
	 *
	 * @param array $mailsIds
	 * @param string $flag which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060.
	 * @return bool
	 */
	public function clearFlag(array $mailsIds, $flag) {
		return imap_clearflag_full($this->getImapStream(), implode(',', $mailsIds), $flag, ST_UID);
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
		$mails = imap_fetch_overview($this->getImapStream(), implode(',', $mailsIds), FT_UID);
		if(is_array($mails) && count($mails))
		{
			foreach($mails as &$mail)
			{
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
	 * @return object Object with info | FALSE on failure
	 */

	public function getMailboxInfo() {
		return imap_mailboxmsginfo($this->getImapStream());
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
		return imap_sort($this->getImapStream(), $criteria, $reverse, SE_UID);
	}

	/**
	 * Get mails count in mail box
	 * @return int
	 */
	public function countMails() {
		return imap_num_msg($this->getImapStream());
	}

	/**
	 * Retrieve the quota settings per user
	 * @return array - FALSE in the case of call failure
	 */
	protected function getQuota() {
		return imap_get_quotaroot($this->getImapStream(), 'INBOX');
	}

	/**
	 * Return quota limit in KB
	 * @return int - FALSE in the case of call failure
	 */
	public function getQuotaLimit() {
		$quota = $this->getQuota();
		if(is_array($quota)) {
			$quota = $quota['STORAGE']['limit'];
		}
		return $quota;
	}

	/**
	 * Return quota usage in KB
	 * @return int - FALSE in the case of call failure
	 */
	public function getQuotaUsage() {
		$quota = $this->getQuota();
		if(is_array($quota)) {
			$quota = $quota['STORAGE']['usage'];
		}
		return $quota;
	}

	/**
	 * Get raw mail data
	 *
	 * @param $msgId
	 * @param bool $markAsSeen
	 * @return mixed
	 */
	public function getRawMail($msgId, $markAsSeen = true){
		$options = FT_UID;
        	if(!$markAsSeen) {
            		$options |= FT_PEEK;
        	}

		return imap_fetchbody($this->getImapStream(), $msgId, '', $options);
	}

    /**
     * Get mail data
     *
     * @param $mailId
     * @param bool $markAsSeen
     * @return IncomingMail
     */
	public function getMail($mailId, $markAsSeen = true) {
		$headersRaw = imap_fetchheader($this->getImapStream(), $mailId, FT_UID);
		$head = imap_rfc822_parse_headers($headersRaw);

		$mail = new IncomingMail();
		$mail->headersRaw = $headersRaw;
		$mail->headers = $head;
		$mail->id = $mailId;
		$mail->date = date('Y-m-d H:i:s', isset($head->date) ? strtotime(preg_replace('/\(.*?\)/', '', $head->date)) : time());
		$mail->subject = isset($head->subject) ? $this->decodeMimeStr($head->subject, $this->serverEncoding) : null;
		$mail->fromName = isset($head->from[0]->personal) ? $this->decodeMimeStr($head->from[0]->personal, $this->serverEncoding) : null;
		$mail->fromAddress = strtolower($head->from[0]->mailbox . '@' . $head->from[0]->host);

		if(isset($head->to)) {
			$toStrings = array();
			foreach($head->to as $to) {
				if(!empty($to->mailbox) && !empty($to->host)) {
					$toEmail = strtolower($to->mailbox . '@' . $to->host);
					$toName = isset($to->personal) ? $this->decodeMimeStr($to->personal, $this->serverEncoding) : null;
					$toStrings[] = $toName ? "$toName <$toEmail>" : $toEmail;
					$mail->to[$toEmail] = $toName;
				}
			}
			$mail->toString = implode(', ', $toStrings);
		}

		if(isset($head->cc)) {
			foreach($head->cc as $cc) {
				$mail->cc[strtolower($cc->mailbox . '@' . $cc->host)] = isset($cc->personal) ? $this->decodeMimeStr($cc->personal, $this->serverEncoding) : null;
			}
		}
		
		if(isset($head->bcc)) {
			foreach($head->bcc as $bcc) {
				$mail->bcc[strtolower($bcc->mailbox . '@' . $bcc->host)] = isset($bcc->personal) ? $this->decodeMimeStr($bcc->personal, $this->serverEncoding) : null;
			}
		}

		if(isset($head->reply_to)) {
			foreach($head->reply_to as $replyTo) {
				$mail->replyTo[strtolower($replyTo->mailbox . '@' . $replyTo->host)] = isset($replyTo->personal) ? $this->decodeMimeStr($replyTo->personal, $this->serverEncoding) : null;
			}
		}

		if(isset($head->message_id)) {
			$mail->messageId = $head->message_id;
		}

		$mailStructure = imap_fetchstructure($this->getImapStream(), $mailId, FT_UID);

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
		$data = $partNum ? imap_fetchbody($this->getImapStream(), $mail->id, $partNum, $options) : imap_body($this->getImapStream(), $mail->id, $options);

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

		$params = array();
		if(!empty($partStructure->parameters)) {
			foreach($partStructure->parameters as $param) {
				$params[strtolower($param->attribute)] = $param->value;
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

		// attachments
		$attachmentId = $partStructure->ifid
			? trim($partStructure->id, " <>")
			: (isset($params['filename']) || isset($params['name']) ? mt_rand() . mt_rand() : null);

		// ignore contentId on body when mail isn't multipart (https://github.com/barbushin/php-imap/issues/71)
		if (!$partNum && TYPETEXT === $partStructure->type)
		{
			$attachmentId = null;
		}

		if($attachmentId) {
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
			$attachment->name = $fileName;
			$attachment->disposition = (isset($partStructure->disposition) ? $partStructure->disposition : null);
			if($this->attachmentsDir) {
				$replace = array(
					'/\s/' => '_',
					'/[^0-9a-zа-яіїє_\.]/iu' => '',
					'/_+/' => '_',
					'/(^_)|(_$)/' => '',
				);
				$fileSysName = preg_replace('~[\\\\/]~', '', $mail->id . '_' . $attachmentId . '_' . preg_replace(array_keys($replace), $replace, $fileName));
				$attachment->filePath = $this->attachmentsDir . DIRECTORY_SEPARATOR . $fileSysName;
				
				if(strlen($attachment->filePath) > 255) {
					$ext = pathinfo($attachment->filePath, PATHINFO_EXTENSION);
					$attachment->filePath = substr($attachment->filePath, 0, 255 -1 -strlen($ext)).".".$ext;
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
				if($partStructure->type == 2 && $partStructure->subtype == 'RFC822') {
					$this->initMailPart($mail, $subPartStructure, $partNum, $markAsSeen);
				}
				else {
					$this->initMailPart($mail, $subPartStructure, $partNum . '.' . ($subPartNum + 1), $markAsSeen);
				}
			}
		}
	}

	protected function decodeMimeStr($string, $charset = 'utf-8') {
		$newString = '';
		$elements = imap_mime_header_decode($string);
		for($i = 0; $i < count($elements); $i++) {
			if($elements[$i]->charset == 'default') {
				$elements[$i]->charset = 'iso-8859-1';
			}
			$newString .= $this->convertStringEncoding($elements[$i]->text, $elements[$i]->charset, $charset);
		}
		return $newString;
	}

	function isUrlEncoded($string) {
		$hasInvalidChars = preg_match( '#[^%a-zA-Z0-9\-_\.\+]#', $string );
		$hasEscapedChars = preg_match( '#%[a-zA-Z0-9]{2}#', $string );
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
	 */
	protected function convertStringEncoding($string, $fromEncoding, $toEncoding) {
		$convertedString = null;
		if($string && $fromEncoding != $toEncoding) {
			$convertedString = @iconv($fromEncoding, $toEncoding . '//IGNORE', $string);
			if(!$convertedString && extension_loaded('mbstring')) {
				$convertedString = @mb_convert_encoding($string, $toEncoding, $fromEncoding);
			}
		}
		return $convertedString ?: $string;
	}

	public function __destruct() {
		$this->disconnect();
	}

	/**
	 * @param $imapPath
	 * @return void
	 */
	protected function setImapPath($imapPath)
	{
		if (function_exists('mb_convert_encoding')) {
			$this->imapPath = mb_convert_encoding($imapPath, "UTF7-IMAP", "UTF-8");
		} else {
			$this->imapPath = imap_utf7_encode($imapPath);
		}
	}
}

class Exception extends \Exception {

}
