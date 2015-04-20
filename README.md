ImapMailbox is PHP class to access mailbox by POP3/IMAP/NNTP using IMAP extension

### Features

* Connect to mailbox by POP3/IMAP/NNTP (see [imap_open](http://php.net/imap_open))
* Get mailbox status (see [imap_check](http://php.net/imap_check))
* Receive emails (+attachments, +html body images)
* Search emails by custom criteria (see [imap_search](http://php.net/imap_search))
* Change email status (see [imap_setflag_full](http://php.net/imap_setflag_full))
* Delete email

### [Usage example](https://github.com/barbushin/php-imap/blob/master/example/index.php)
```php
<?php

require_once('../src/ImapMailbox.php');

// IMAP must be enabled in Google Mail Settings
define('GMAIL_EMAIL', 'some@gmail.com');
define('GMAIL_PASSWORD', '*********');
define('ATTACHMENTS_DIR', __DIR__);

$mailbox = new ImapMailbox('{imap.gmail.com:993/imap/ssl}INBOX', GMAIL_EMAIL, GMAIL_PASSWORD, ATTACHMENTS_DIR);
$mails = array();

// Get some mail
$mailsIds = $mailbox->searchMailBox('ALL');
if(!$mailsIds) {
	die('Mailbox is empty');
}

$mailId = reset($mailsIds);
$mail = $mailbox->getMail($mailId);

var_dump($mail);
var_dump($mail->getAttachments());
```

### Recommended

* Google Chrome extension [PHP Console](https://chrome.google.com/webstore/detail/php-console/nfhmhhlpfleoednkpnnnkolmclajemef)
* Google Chrome extension [JavaScript Errors Notifier](https://chrome.google.com/webstore/detail/javascript-errors-notifie/jafmfknfnkoekkdocjiaipcnmkklaajd)
