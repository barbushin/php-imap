# PHP IMAP

[![GitHub release](https://img.shields.io/github/release/barbushin/php-imap.svg?maxAge=86400&style=flat-square)](https://packagist.org/packages/php-imap/php-imap)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/php-imap/php-imap.svg?style=flat-square)](https://packagist.org/packages/php-imap/php-imap)
[![Build Status](https://travis-ci.org/barbushin/php-imap.svg?branch=master)](https://travis-ci.org/barbushin/php-imap)
[![Supported PHP Version](https://img.shields.io/packagist/php-v/php-imap/php-imap/3.0.8.svg)](README.md)

Initially released in December 2012, the PHP IMAP Mailbox is a powerful and open source library to connect to a mailbox by POP3, IMAP and NNTP using the PHP IMAP extension. This library allows you to fetch emails from your email server. Extend the functionality or create powerful web applications to handle your incoming emails.

### Features

* Connect to mailbox by POP3/IMAP/NNTP, using [PHP IMAP extension](http://php.net/manual/book.imap.php)
* Get emails with attachments and inline images
* Get emails filtered or sorted by custom criteria
* Mark emails as seen/unseen
* Delete emails
* Manage mailbox folders
 
### Requirements

* PHP 5.6, 7.0, 7.1, 7.2 or 7.3
* IMAP extension must be present; so make sure this line is active in your php.ini: `extension=php_imap.dll`

### Installation by Composer

Install the [latest available release](https://github.com/barbushin/php-imap/releases):

	$ composer require php-imap/php-imap

Install the latest available and stable source code from `master`, which is may not released / tagged yet:

	$ composer require php-imap/php-imap

Install the latest available and may unstable source code from `develop`, which is may not properly tested yet:

	$ composer require php-imap/php-imap:dev-develop

### PHPUnit Tests

You can run all PHPUnit tests by running the following command (inside of the installed `php-imap` directory): `php vendor/bin/phpunit --testdox`

Before you can run the PHPUnit tests you may need to run `composer install` to install all (development) dependencies. 

### Integration with frameworks

* Symfony - https://github.com/secit-pl/imap-bundle

### Getting Started Example

Below, you'll find an example code how you can use this library. For further information and other examples, you may take a look at the [wiki](https://github.com/barbushin/php-imap/wiki).

```php
// Create PhpImap\Mailbox instance for all further actions
$mailbox = new PhpImap\Mailbox(
	'{imap.gmail.com:993/imap/ssl}INBOX', // IMAP server and mailbox folder
	'some@gmail.com', // Username for the before configured mailbox
	'*********', // Password for the before configured username
	__DIR__, // Directory, where attachments will be saved (optional)
	'UTF-8' // Server encoding (optional)
);

try {
	// Get all emails (messages)
	// PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
	$mailsIds = $mailbox->searchMailbox('ALL');
} catch(PhpImap\ConnectionException $ex) {
	echo "IMAP connection failed: " . $ex;
	die();
}

// If $mailsIds is empty, no emails could be found
if(!$mailsIds) {
	die('Mailbox is empty');
}

// Get the first message
// If '__DIR__' was defined in the first line, it will automatically
// save all attachments to the specified directory
$mail = $mailbox->getMail($mailsIds[0]);

// Print all information of $mail
print_r($mail);

// Print all attachements of $mail
echo "\n\nAttachments:\n";
print_r($mail->getAttachments());
```

### Recommended

* Google Chrome extension [PHP Console](https://chrome.google.com/webstore/detail/php-console/nfhmhhlpfleoednkpnnnkolmclajemef)
* Google Chrome extension [JavaScript Errors Notifier](https://chrome.google.com/webstore/detail/javascript-errors-notifie/jafmfknfnkoekkdocjiaipcnmkklaajd)
