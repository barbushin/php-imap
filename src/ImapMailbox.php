<?php

/**
 * @see https://github.com/barbushin/php-imap
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 */
class ImapMailbox
{

    protected $imapPath;
    protected $login;
    protected $password;
    protected $serverEncoding;
    protected $attachmentsDir;

    public function __construct($imapPath, $login, $password, $attachmentsDir = null, $serverEncoding = 'utf-8')
    {
        $this->imapPath = $imapPath;
        $this->login = $login;
        $this->password = $password;
        $this->serverEncoding = $serverEncoding;
        if ($attachmentsDir) {
            if (!is_dir($attachmentsDir)) {
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
    public function getImapStream($forceConnection = true)
    {
        static $imapStream;
        if ($forceConnection) {
            if ($imapStream && (!is_resource($imapStream) || !imap_ping($imapStream))) {
                $this->disconnect();
                $imapStream = null;
            }
            if (!$imapStream) {
                $imapStream = $this->initImapStream();
            }
        }
        return $imapStream;
    }

    protected function initImapStream()
    {
        $imapStream = @imap_open($this->imapPath, $this->login, $this->password);
        if (!$imapStream) {
            throw new ImapMailboxException('Connection error: ' . imap_last_error());
        }
        return $imapStream;
    }

    protected function disconnect()
    {
        $imapStream = $this->getImapStream(false);
        if ($imapStream && is_resource($imapStream)) {
            imap_close($imapStream, CL_EXPUNGE);
        }
    }

    /**
     * Get information about the current mailbox.
     *
     * Returns the information in an object with following properties:
     *    Date - current system time formatted according to RFC2822
     *    Driver - protocol used to access this mailbox: POP3, IMAP, NNTP
     *    Mailbox - the mailbox name
     *    Nmsgs - number of mails in the mailbox
     *    Recent - number of recent mails in the mailbox
     *
     * @return stdClass
     */
    public function checkMailbox()
    {
        return imap_check($this->getImapStream());
    }

    /**
     * This function performs a search on the mailbox currently opened in the given IMAP stream.
     * For example, to match all unanswered mails sent by Mom, you'd use: "UNANSWERED FROM mom".
     * Searches appear to be case insensitive. This list of criteria is from a reading of the UW
     * c-client source code and may be incomplete or inaccurate (see also RFC2060, section 6.4.4).
     *
     * @param string $criteria String, delimited by spaces, in which the following keywords are allowed. Any multi-word arguments (e.g. FROM "joey smith") must be quoted. Results will match all criteria entries.
     *        ALL - return all mails matching the rest of the criteria
     *        ANSWERED - match mails with the \\ANSWERED flag set
     *        BCC "string" - match mails with "string" in the Bcc: field
     *        BEFORE "date" - match mails with Date: before "date"
     *        BODY "string" - match mails with "string" in the body of the mail
     *        CC "string" - match mails with "string" in the Cc: field
     *        DELETED - match deleted mails
     *        FLAGGED - match mails with the \\FLAGGED (sometimes referred to as Important or Urgent) flag set
     *        FROM "string" - match mails with "string" in the From: field
     *        KEYWORD "string" - match mails with "string" as a keyword
     *        NEW - match new mails
     *        OLD - match old mails
     *        ON "date" - match mails with Date: matching "date"
     *        RECENT - match mails with the \\RECENT flag set
     *        SEEN - match mails that have been read (the \\SEEN flag is set)
     *        SINCE "date" - match mails with Date: after "date"
     *        SUBJECT "string" - match mails with "string" in the Subject:
     *        TEXT "string" - match mails with text "string"
     *        TO "string" - match mails with "string" in the To:
     *        UNANSWERED - match mails that have not been answered
     *        UNDELETED - match mails that are not deleted
     *        UNFLAGGED - match mails that are not flagged
     *        UNKEYWORD "string" - match mails that do not have the keyword "string"
     *        UNSEEN - match mails which have not been read yet
     *
     * @return array Mails ids
     */
    public function searchMailbox($criteria = 'ALL')
    {
        $mailsIds = imap_search($this->getImapStream(), $criteria, SE_UID, $this->serverEncoding);
        return $mailsIds ? $mailsIds : array();
    }

    /**
     * Marks mails listed in mailId for deletion.
     *
     * @param $mailId
     * @return bool
     */
    public function deleteMail($mailId)
    {
        return imap_delete($this->getImapStream(), $mailId, FT_UID);
    }

    /**
     * Move the mail in other folder
     *
     * @param $mailId
     * @param $where - Name of the folder where to move the email
     * @return bool
     */
    public function moveMail($mailId, $where)
    {
        if (!$r = imap_mail_move($this->getImapStream(), $mailId, $where, CP_UID)) {
            return false;
        }
        return $this->expungeDeletedMails();
    }

    /**
     * Deletes all the mails marked for deletion by imap_delete(), imap_mail_move(), or imap_setflag_full().
     *
     * @return bool
     */
    public function expungeDeletedMails()
    {
        return imap_expunge($this->getImapStream());
    }

    /**
     * @param $mailId
     */
    public function markMailAsRead($mailId)
    {
        $this->setFlag($mailId, '\\Seen');
    }

    /**
     * @param $mailId
     */
    public function markMailAsUnread($mailId)
    {
        $this->clearFlag($mailId, '\\Seen');
    }

    /**
     * @param $mailId
     */
    public function markMailAsImportant($mailId)
    {
        $this->setFlag($mailId, '\\Flagged');
    }

    /**
     * Causes a store to add the specified flag to the flags set for the mails in the specified sequence.
     *
     * @param array $mailsIds
     * @param $flag - Flags which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060.
     * @return bool
     */
    public function setFlag(array $mailsIds, $flag)
    {
        return imap_setflag_full($this->getImapStream(), implode(',', $mailsIds), $flag, ST_UID);
    }

    /**
     * Cause a store to delete the specified flag to the flags set for the mails in the specified sequence.
     *
     * @param array $mailsIds
     * @param $flag - Flags which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060.
     * @return bool
     */
    public function clearFlag(array $mailsIds, $flag)
    {
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
    public function getMailsInfo(array $mailsIds)
    {
        return imap_fetch_overview($this->getImapStream(), implode(',', $mailsIds), FT_UID);
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

    public function getMailboxInfo()
    {
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
    public function sortMails($criteria = SORTARRIVAL, $reverse = true)
    {
        return imap_sort($this->getImapStream(), $criteria, $reverse, SE_UID);
    }

    /**
     * Get mails count in mail box
     * @return int
     */
    public function countMails()
    {
        return imap_num_msg($this->getImapStream());
    }

    /**
     * Get mail data
     *
     * @param $mailId
     * @return IncomingMail
     */
    public function getMail($mailId)
    {
        $head = imap_rfc822_parse_headers(imap_fetchheader($this->getImapStream(), $mailId, FT_UID));

        $mail = new IncomingMail();
        $mail->id = $mailId;
        $mail->date = date('Y-m-d H:i:s', isset($head->date) ? strtotime($head->date) : time());
        $mail->subject = $this->decodeMimeStr($head->subject, $this->serverEncoding);
        $mail->fromName = isset($head->from[0]->personal) ? $this->decodeMimeStr($head->from[0]->personal, $this->serverEncoding) : null;
        $mail->fromAddress = strtolower($head->from[0]->mailbox . '@' . $head->from[0]->host);

        $toStrings = array();
        foreach ($head->to as $to) {
            if (!empty($to->mailbox) && !empty($to->host)) {
                $toEmail = strtolower($to->mailbox . '@' . $to->host);
                $toName = isset($to->personal) ? $this->decodeMimeStr($to->personal, $this->serverEncoding) : null;
                $toStrings[] = $toName ? "$toName <$toEmail>" : $toEmail;
                $mail->to[$toEmail] = $toName;
            }
        }
        $mail->toString = implode(', ', $toStrings);

        if (isset($head->cc)) {
            foreach ($head->cc as $cc) {
                $mail->cc[strtolower($cc->mailbox . '@' . $cc->host)] = isset($cc->personal) ? $this->decodeMimeStr($cc->personal, $this->serverEncoding) : null;
            }
        }

        if (isset($head->reply_to)) {
            foreach ($head->reply_to as $replyTo) {
                $mail->replyTo[strtolower($replyTo->mailbox . '@' . $replyTo->host)] = isset($replyTo->personal) ? $this->decodeMimeStr($replyTo->personal, $this->serverEncoding) : null;
            }
        }

        $mailStructure = imap_fetchstructure($this->getImapStream(), $mailId, FT_UID);

        if (empty($mailStructure->parts)) {
            $this->initMailPart($mail, $mailStructure, 0);
        } else {
            foreach ($mailStructure->parts as $partNum => $partStructure) {
                $this->initMailPart($mail, $partStructure, $partNum + 1);
            }
        }

        return $mail;
    }

    /**
     * @param IncomingMail $mail
     * @param $partStructure
     * @param $partNum
     */
    protected function initMailPart(IncomingMail $mail, $partStructure, $partNum)
    {
        $data = $partNum ? imap_fetchbody($this->getImapStream(), $mail->id, $partNum, FT_UID) : imap_body($this->getImapStream(), $mail->id, FT_UID);

        if ($partStructure->encoding == 1) {
            $data = imap_utf8($data);
        } elseif ($partStructure->encoding == 2) {
            $data = imap_binary($data);
        } elseif ($partStructure->encoding == 3) {
            $data = imap_base64($data);
        } elseif ($partStructure->encoding == 4) {
            $data = imap_qprint($data);
        }

        $params = array();
        if (!empty($partStructure->parameters)) {
            foreach ($partStructure->parameters as $param) {
                $params[strtolower($param->attribute)] = $param->value;
            }
        }
        if (!empty($partStructure->dparameters)) {
            foreach ($partStructure->dparameters as $param) {
                $paramName = strtolower(preg_match('~^(.*?)\*~', $param->attribute, $matches) ? $matches[1] : $param->attribute);
                if (isset($params[$paramName])) {
                    $params[$paramName] .= $param->value;
                } else {
                    $params[$paramName] = $param->value;
                }
            }
        }
        if (!empty($params['charset'])) {
            $data = iconv(strtoupper($params['charset']), $this->serverEncoding, $data);
        }

        // attachments
        $attachmentId = $partStructure->ifid
            ? trim($partStructure->id, " <>")
            : (isset($params['filename']) || isset($params['name']) ? mt_rand() . mt_rand() : null);
        if ($attachmentId) {
            if (empty($params['filename']) && empty($params['name'])) {
                $fileName = $attachmentId . '.' . strtolower($partStructure->subtype);
            } else {
                $fileName = !empty($params['filename']) ? $params['filename'] : $params['name'];
                $fileName = $this->decodeMimeStr($fileName, $this->serverEncoding);
                $fileName = $this->decodeRFC2231($fileName, $this->serverEncoding);
            }
            $attachment = new IncomingMailAttachment();
            $attachment->id = $attachmentId;
            $attachment->name = $fileName;
            if ($this->attachmentsDir) {
                $replace = array(
                    '/\s/' => '_',
                    '/[^0-9a-zA-Z_\.]/' => '',
                    '/_+/' => '_',
                    '/(^_)|(_$)/' => '',
                );
                $fileSysName = preg_replace('~[\\\\/]~', '', $mail->id . '_' . $attachmentId . '_' . preg_replace(array_keys($replace), $replace, $fileName));
                $attachment->filePath = $this->attachmentsDir . DIRECTORY_SEPARATOR . $fileSysName;
                file_put_contents($attachment->filePath, $data);
            }
            $mail->addAttachment($attachment);
        } elseif ($partStructure->type == 0 && $data) {
            if (strtolower($partStructure->subtype) == 'plain') {
                $mail->textPlain .= $data;
            } else {
                $mail->textHtml .= $data;
            }
        } elseif ($partStructure->type == 2 && $data) {
            $mail->textPlain .= trim($data);
        }
        if (!empty($partStructure->parts)) {
            foreach ($partStructure->parts as $subPartNum => $subPartStructure) {
                if ($partStructure->type == 2 && $partStructure->subtype == 'RFC822') {
                    $this->initMailPart($mail, $subPartStructure, $partNum);
                } else {
                    $this->initMailPart($mail, $subPartStructure, $partNum . '.' . ($subPartNum + 1));
                }
            }
        }
    }

    /**
     * @param $string
     * @param string $charset
     * @return string
     */
    protected function decodeMimeStr($string, $charset = 'utf-8')
    {
        $newString = '';
        $elements = imap_mime_header_decode($string);
        for ($i = 0; $i < count($elements); $i++) {
            if ($elements[$i]->charset == 'default') {
                $elements[$i]->charset = 'iso-8859-1';
            }
            $newString .= iconv(strtoupper($elements[$i]->charset), $charset, $elements[$i]->text);
        }
        return $newString;
    }

    /**
     * @param $string
     * @return bool
     */
    function isUrlEncoded($string)
    {
        $string = str_replace('%20', '+', $string);
        $decoded = urldecode($string);
        return $decoded != $string && urlencode($decoded) == $string;
    }

    /**
     * @param $string
     * @param string $charset
     * @return string
     */
    protected function decodeRFC2231($string, $charset = 'utf-8')
    {
        if (preg_match("/^(.*?)'.*?'(.*?)$/", $string, $matches)) {
            $encoding = $matches[1];
            $data = $matches[2];
            if ($this->isUrlEncoded($data)) {
                $string = iconv(strtoupper($encoding), $charset, urldecode($data));
            }
        }
        return $string;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}


class IncomingMail
{

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
    /** @var IncomingMailAttachment[] */
    protected $attachments = array();

    public function addAttachment(IncomingMailAttachment $attachment)
    {
        $this->attachments[$attachment->id] = $attachment;
    }

    /**
     * @return IncomingMailAttachment[]
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * Get array of internal HTML links placeholders
     * @return array attachmentId => link placeholder
     */
    public function getInternalLinksPlaceholders()
    {
        return preg_match_all('/=["\'](ci?d:(\w+))["\']/i', $this->textHtml, $matches) ? array_combine($matches[2], $matches[1]) : array();
    }

    /**
     * @param $baseUri
     * @return mixed
     */
    public function replaceInternalLinks($baseUri)
    {
        $baseUri = rtrim($baseUri, '\\/') . '/';
        $fetchedHtml = $this->textHtml;
        foreach ($this->getInternalLinksPlaceholders() as $attachmentId => $placeholder) {
            $fetchedHtml = str_replace($placeholder, $baseUri . basename($this->attachments[$attachmentId]->filePath), $fetchedHtml);
        }
        return $fetchedHtml;
    }
}


class IncomingMailAttachment
{
    public $id;
    public $name;
    public $filePath;
}


class ImapMailboxException extends Exception
{

}
