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
		if($attachmentsDir) {
			if(!is_dir($attachmentsDir)) {
				throw new Exception('Directory "' . $attachmentsDir . '" not found');
			}
			$this->attachmentsDir = realpath($attachmentsDir);
		}

		$this->connect();
	}

	protected function connect() {
		$this->mbox = @$this->imap_open($this->imapPath, $this->login, $this->password);
		if(!$this->mbox) {
			throw new ImapMailboxException('Connection error: ' . imap_last_error());
		}
	}

	protected function checkConnection() {
		if(!$this->imap_ping($this->mbox)) {
			$this->reconnect();
		}
	}

	protected function reconnect() {
		$this->closeConnection();
		$this->connect();
	}

	public function getCheck() {
		$this->checkConnection();
		$result = $this->imap_check($this->mbox);

		return $result;
	}

	public function searchMails($imapCriteria = 'ALL') {
		$this->checkConnection();
		$mailsIds = $this->imap_search($this->mbox, $imapCriteria, SE_UID, $this->serverEncoding);

		return $mailsIds ? $mailsIds : array();
	}

	public function deleteMail($mId) {
		$this->checkConnection();
		$this->imap_delete($this->mbox, $mId, FT_UID | CL_EXPUNGE);
		$this->imap_expunge($this->mbox);
		return true;
	}

	public function setMailAsSeen($mId) {
		$this->checkConnection();
		$this->setMailImapFlag($mId, '\\Seen');
	}

	public function setMailImapFlag($mId, $flag) {
		$this->imap_setflag_full($this->mbox, $mId, $flag, ST_UID);
	}

	protected function getMailHeaders($mId) {
		$this->checkConnection();
		$headers = $this->imap_fetchheader($this->mbox, $mId, FT_UID);

		if(!$headers) {
			throw new ImapMailboxException('Message with UID "' . $mId . '" not found');
		}
		return $headers;
	}

	public function getMail($mId) {
		$this->checkConnection();
		$head = $this->imap_rfc822_parse_headers($this->getMailHeaders($mId));

		$mail = new IncomingMail();
		$mail->mId = $mId;
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

		$struct = $this->imap_fetchstructure($this->mbox, $mId, FT_UID);

		if(empty($struct->parts)) {
			$this->initMailPart($mail, $struct, 0);
		}
		else {
			foreach($struct->parts as $partNum => $partStruct) {
				$this->initMailPart($mail, $partStruct, $partNum + 1);
			}
		}

		$mail->textHtmlOriginal = $mail->textHtml;

		return $mail;
	}

	protected function quoteAttachmentFilename($filename) {
		$replace = array('/\s/' => '_', '/[^0-9a-zA-Z_\.]/' => '', '/_+/' => '_', '/(^_)|(_$)/' => '');

		return preg_replace(array_keys($replace), $replace, $filename);
	}

	protected function initMailPart(IncomingMail $mail, $partStruct, $partNum) {
		$data = $partNum ? $this->imap_fetchbody($this->mbox, $mail->mId, $partNum, FT_UID) : $this->imap_body($this->mbox, $mail->mId, FT_UID);

		if($partStruct->encoding == 1) {
			$data = $this->imap_utf8($data);
		}
		elseif($partStruct->encoding == 2) {
			$data = $this->imap_binary($data);
		}
		elseif($partStruct->encoding == 3) {
			$data = $this->imap_base64($data);
		}
		elseif($partStruct->encoding == 4) {
			$data = $this->imap_qprint($data);
		}

		$params = array();
		if(!empty($partStruct->parameters)) {
			foreach($partStruct->parameters as $param) {
				$params[strtolower($param->attribute)] = $param->value;
			}
		}
		if(!empty($partStruct->dparametersx)) {
			foreach($partStruct->dparameters as $param) {
				$params[strtolower($param->attribute)] = $param->value;
			}
		}
		if(!empty($params['charset'])) {
			$data = iconv($params['charset'], $this->serverEncoding, $data);
		}

		// attachments
		if($this->attachmentsDir) {
			$filename = false;
			$attachmentId = $partStruct->ifid ? trim($partStruct->id, " <>") : null;
			if(empty($params['filename']) && empty($params['name']) && $attachmentId) {
				$filename = $attachmentId . '.' . strtolower($partStruct->subtype);
			}
			elseif(!empty($params['filename']) || !empty($params['name'])) {
				$filename = !empty($params['filename']) ? $params['filename'] : $params['name'];
				$filename = $this->decodeMimeStr($filename);
				$filename = $this->quoteAttachmentFilename($filename);
			}
			if($filename) {
				if($this->attachmentsDir) {
					$filepath = rtrim($this->attachmentsDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
					file_put_contents($filepath, $data);
					$mail->attachments[$filename] = $filepath;
				}
				else {
					$mail->attachments[$filename] = $filename;
				}
				if($attachmentId) {
					$mail->attachmentsIds[$filename] = $attachmentId;
				}
			}
		}
		if($partStruct->type == 0 && $data) {
			if(strtolower($partStruct->subtype) == 'plain') {
				$mail->textPlain .= $data;
			}
			else {
				$mail->textHtml .= $data;
			}
		}
		elseif($partStruct->type == 2 && $data) {
			$mail->textPlain .= trim($data);
		}
		if(!empty($partStruct->parts)) {
			foreach($partStruct->parts as $subpartNum => $subpartStruct) {
				$this->initMailPart($mail, $subpartStruct, $partNum . '.' . ($subpartNum + 1));
			}
		}
	}

	protected function decodeMimeStr($string, $charset = 'UTF-8') {
		$newString = '';
		$elements = $this->imap_mime_header_decode($string);
		for($i = 0; $i < count($elements); $i++) {
			if($elements[$i]->charset == 'default') {
				$elements[$i]->charset = 'iso-8859-1';
			}
			$newString .= iconv($elements[$i]->charset, $charset, $elements[$i]->text);
		}
		return $newString;
	}

	protected function closeConnection() {
		if($this->mbox) {
			$errors = imap_errors();
			if($errors) {
				foreach($errors as $error) {
					trigger_error($error);
				}
			}
			imap_close($this->mbox);
		}
	}

	public function __call($imapFunction, $args) {
		$result = call_user_func_array($imapFunction, $args);
		$errors = imap_errors();
		if($errors) {
			foreach($errors as $error) {
				trigger_error($error);
			}
		}
		return $result;
	}

	public function __destruct() {
		$this->closeConnection();
	}
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
		if($this->textHtml) {
			foreach($this->attachments as $filepath) {
				$filename = basename($filepath);
				if(isset($this->attachmentsIds[$filename])) {
					$this->textHtml = preg_replace('/(<img[^>]*?)src=["\']?ci?d:' . preg_quote($this->attachmentsIds[$filename]) . '["\']?/is', '\\1 src="' . $baseUrl . $filename . '"', $this->textHtml);
				}
			}
		}
	}

	public function fetchMessageHtmlTags($stripTags = array('html', 'body', 'head', 'meta')) {
		if($this->textHtml) {
			foreach($stripTags as $tag) {
				$this->textHtml = preg_replace('/<\/?' . $tag . '.*?>/is', '', $this->textHtml);
			}
			$this->textHtml = trim($this->textHtml, " \r\n");
		}
	}
}

class ImapMailboxException extends Exception {
}
