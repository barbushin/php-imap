ImapMailbox is PHP class to access mailbox by POP3/IMAP/NNTP using IMAP extension

### Features

* Connect to mailbox by POP3/IMAP/NNTP (see [imap_open](http://php.net/imap_open))
* Get mailbox status (see [imap_check](http://php.net/imap_check))
* Receive emails (+attachments, +html body images)
* Search emails by custom criteria (see [imap_search](http://php.net/imap_search))
* Change email status (see [imap_setflag_full](http://php.net/imap_setflag_full))
* Delete email

### Installation by Composer

	{
		"require": {
			"php-imap/php-imap": "~2.0"
		}
	}

Or

	$ composer require php-imap/php-imap ~2.0

### Migration from `v1.*` to `v2.*`

Just add following code in the head of your script:

	use PhpImap\Mailbox as ImapMailbox;
	use PhpImap\IncomingMail;
	use PhpImap\IncomingMailAttachment;

### Usage example

```php
$mailbox = new ImapMailbox('{imap.gmail.com:993/imap/ssl}INBOX', 'some@gmail.com', '*********', __DIR__);
$mails = array();

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
