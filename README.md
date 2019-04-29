# PHP IMAP

[![GitHub release](https://img.shields.io/github/release/barbushin/php-imap.svg?maxAge=86400&style=flat-square)](https://packagist.org/packages/php-imap/php-imap)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/php-imap/php-imap.svg?style=flat-square)](https://packagist.org/packages/php-imap/php-imap)
[![Build Status](https://travis-ci.org/barbushin/php-imap.svg?branch=master)](https://travis-ci.org/barbushin/php-imap)
[![Supported PHP Version](https://img.shields.io/packagist/php-v/php-imap/php-imap/3.0.8.svg)](README.md)

### Features

* Connect to mailbox by POP3/IMAP/NNTP, using [PHP IMAP extension](http://php.net/manual/book.imap.php)
* Get emails with attachments and inline images
* Get emails filtered or sorted by custom criteria
* Mark emails as seen/unseen
* Delete emails
* Manage mailbox folders
 
### Requirements

* PHP 7.1, 7.2
* IMAP extension must be present; so make sure this line is active in your php.ini: `extension=php_imap.dll`

### Installation by Composer

	$ composer require php-imap/php-imap
	
### Integration with frameworks

* Symfony - https://github.com/secit-pl/imap-bundle

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

print_r($mail);
echo "\n\nAttachments:\n";
print_r($mail->getAttachments());
```

### Recommended

* Google Chrome extension [PHP Console](https://chrome.google.com/webstore/detail/php-console/nfhmhhlpfleoednkpnnnkolmclajemef)
* Google Chrome extension [JavaScript Errors Notifier](https://chrome.google.com/webstore/detail/javascript-errors-notifie/jafmfknfnkoekkdocjiaipcnmkklaajd)
