ImapMailbox is a PHP class to access mailboxes by POP3/IMAP/NNTP using PHP's native IMAP extension.

### Features

* Connect to mailbox by POP3/IMAP/NNTP, using [imap_open()](http://php.net/imap_open)
* Get mailbox status, usinge [imap_check()](http://php.net/imap_check)
* Receive emails (+attachments, +html body images)
* Search emails by custom criteria, using [imap_search()](http://php.net/imap_search)
* Change email status, using [imap_setflag_full()](http://php.net/imap_setflag_full)
* Delete email, using [imap_delete()](http://php.net/imap_delete)
 
### Requirements

* IMAP extension must be present; so make sure this line is active in your php.ini: `extension=php_imap.dll`

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
// 4. argument is the directory into which attachments are to be saved:
$mailbox = new PhpImap\Mailbox('{imap.gmail.com:993/imap/ssl}INBOX', 'some@gmail.com', '*********', __DIR__);

// Read all messaged into an array:
$mailsIds = $mailbox->searchMailbox('ALL');
if(!$mailsIds) {
	die('Mailbox is empty');
}

// Get the first message and save its attachment(s) to disk:
$mail = $mailbox->getMail($mailsIds[0]);

var_dump($mail);
echo "\n\n\n\n\n";
var_dump($mail->getAttachments());
```

### Recommended

* Google Chrome extension [PHP Console](https://chrome.google.com/webstore/detail/php-console/nfhmhhlpfleoednkpnnnkolmclajemef)
* Google Chrome extension [JavaScript Errors Notifier](https://chrome.google.com/webstore/detail/javascript-errors-notifie/jafmfknfnkoekkdocjiaipcnmkklaajd)
