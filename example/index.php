<?php

require_once('../ImapMailbox.php');

// IMAP must be enabled in Google Mail Settings
define('GMAIL_EMAIL', 'some@gmail.com');
define('GMAIL_PASSWORD', 'somepassword');
define('ATTACHMENTS_DIR', dirname(__FILE__) . '/attachments');

$mailbox = new ImapMailbox('{imap.gmail.com:993/imap/novalidate-cert/ssl}INBOX', GMAIL_EMAIL, GMAIL_PASSWORD, ATTACHMENTS_DIR, 'utf-8');
$mails = array();

foreach($mailbox->searchMails('ALL') as $mailId) {
	$mail = $mailbox->getMail($mailId);
	// $mailbox->setMailAsSeen($mail->mId);
	// $mailbox->deleteMail($mail->mId);
	$mails[] = $mail;
}

var_dump($mails);