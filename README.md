# PHP IMAP

[![GitHub release](https://img.shields.io/github/release/barbushin/php-imap.svg?style=flat-square)](https://packagist.org/packages/php-imap/php-imap)
[![Supported PHP Version](https://img.shields.io/packagist/php-v/php-imap/php-imap.svg)](README.md)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/php-imap/php-imap.svg?style=flat-square)](https://packagist.org/packages/php-imap/php-imap)

[![CI PHP Unit Tests](https://github.com/barbushin/php-imap/actions/workflows/php_unit_tests.yml/badge.svg?branch=master)](https://github.com/barbushin/php-imap/actions/workflows/php_unit_tests.yml)
[![CI PHP Static Analysis](https://github.com/barbushin/php-imap/actions/workflows/php_static_analysis.yml/badge.svg?branch=master)](https://github.com/barbushin/php-imap/actions/workflows/php_static_analysis.yml)
[![CI PHP Code Coverage](https://github.com/barbushin/php-imap/actions/workflows/php_code_coverage.yml/badge.svg?branch=master)](https://github.com/barbushin/php-imap/actions/workflows/php_code_coverage.yml)

[![Maintainability](https://api.codeclimate.com/v1/badges/02f72a4fd695cb7e2976/maintainability)](https://codeclimate.com/github/barbushin/php-imap/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/02f72a4fd695cb7e2976/test_coverage)](https://codeclimate.com/github/barbushin/php-imap/test_coverage)
[![Type Coverage](https://shepherd.dev/github/barbushin/php-imap/coverage.svg)](https://shepherd.dev/github/barbushin/php-imap)

Initially released in December 2012, the PHP IMAP Mailbox is a powerful and open source library to connect to a mailbox by POP3, IMAP and NNTP using the PHP IMAP extension. This library allows you to fetch emails from your email server. Extend the functionality or create powerful web applications to handle your incoming emails.

### Features

* Connect to mailbox by POP3/IMAP/NNTP, using [PHP IMAP extension](http://php.net/manual/book.imap.php)
* Get emails with attachments and inline images
* Get emails filtered or sorted by custom criteria
* Mark emails as seen/unseen
* Delete emails
* Manage mailbox folders

### Requirements

| PHP Version  | php-imap Version | php-imap status |
| ------------- | ------------- | ------------- |
| 5.6  | 3.x  | End of life |
| 7.0  | 3.x  | End of life |
| 7.1  | 3.x  | End of life |
| 7.2  | 3.x, 4.x | End of life |
| 7.3  | 3.x, 4.x | End of life |
| 7.4  | >3.0.33, 4.x, 5.x | Active support |
| 8.0  | >3.0.33, 4.x, 5.x | Active support |
| 8.1  | >4.3.0, 5.x | Active support |

* PHP `fileinfo` extension must be present; so make sure this line is active in your php.ini: `extension=php_fileinfo.dll`
* PHP `iconv` extension must be present; so make sure this line is active in your php.ini: `extension=php_iconv.dll`
* PHP `imap` extension must be present; so make sure this line is active in your php.ini: `extension=php_imap.dll`
* PHP `mbstring` extension must be present; so make sure this line is active in your php.ini: `extension=php_mbstring.dll`
* PHP `json` extension must be present; so make sure this line is active in your php.ini: `extension=json.dll`

### Installation by Composer

Install the [latest available release](https://github.com/barbushin/php-imap/releases):

	$ composer require php-imap/php-imap

Install the latest available and stable source code from `master`, which is may not released / tagged yet:

	$ composer require php-imap/php-imap:dev-master

### Run Tests

Before you can run the any tests you may need to run `composer install` to install all (development) dependencies.

#### Run all tests

You can run all available tests by running the following command (inside of the installed `php-imap` directory): `composer run tests`

#### Run only PHPUnit tests

You can run all PHPUnit tests by running the following command (inside of the installed `php-imap` directory): `php vendor/bin/phpunit --testdox`

### Integration with frameworks

* Symfony - https://github.com/secit-pl/imap-bundle

### Getting Started Example

Below, you'll find an example code how you can use this library. For further information and other examples, you may take a look at the [wiki](https://github.com/barbushin/php-imap/wiki).

By default, this library uses random filenames for attachments as identical file names from other emails would overwrite other attachments. If you want to keep the original file name, you can set the attachment filename mode to ``true``, but then you also need to ensure, that those files don't get overwritten by other emails for example.

```php
// Create PhpImap\Mailbox instance for all further actions
$mailbox = new PhpImap\Mailbox(
	'{imap.gmail.com:993/imap/ssl}INBOX', // IMAP server and mailbox folder
	'some@gmail.com', // Username for the before configured mailbox
	'*********', // Password for the before configured username
	__DIR__, // Directory, where attachments will be saved (optional)
	'UTF-8', // Server encoding (optional)
    true, // Trim leading/ending whitespaces of IMAP path (optional)
    false // Attachment filename mode (optional; false = random filename; true = original filename)
);

// set some connection arguments (if appropriate)
$mailbox->setConnectionArgs(
    CL_EXPUNGE // expunge deleted mails upon mailbox close
    | OP_SECURE // don't do non-secure authentication
);

try {
	// Get all emails (messages)
	// PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
	$mailsIds = $mailbox->searchMailbox('ALL');
} catch(PhpImap\Exceptions\ConnectionException $ex) {
	echo "IMAP connection failed: " . implode(",", $ex->getErrors('all'));
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

// Show, if $mail has one or more attachments
echo "\nMail has attachments? ";
if($mail->hasAttachments()) {
	echo "Yes\n";
} else {
	echo "No\n";
}

// Print all information of $mail
print_r($mail);

// Print all attachements of $mail
echo "\n\nAttachments:\n";
print_r($mail->getAttachments());
```

Method `imap()` allows to call any [PHP IMAP function](https://www.php.net/manual/ref.imap.php) in a context of the instance. Example:

```php
// Call imap_check() - see http://php.net/manual/function.imap-check.php
$info = $mailbox->imap('check');


// Show current time for the mailbox
$currentServerTime = isset($info->Date) && $info->Date ? date('Y-m-d H:i:s', strtotime($info->Date)) : 'Unknown';

echo $currentServerTime;
```

Some request require much time and resources:

```php
// If you don't need to grab attachments you can significantly increase performance of your application
$mailbox->setAttachmentsIgnore(true);

// get the list of folders/mailboxes
$folders = $mailbox->getMailboxes('*');

// loop through mailboxs
foreach($folders as $folder) {

	// switch to particular mailbox
	$mailbox->switchMailbox($folder['fullpath']);

	// search in particular mailbox
	$mails_ids[$folder['fullpath']] = $mailbox->searchMailbox('SINCE "1 Jan 2018" BEFORE "28 Jan 2018"');
}

print_r($mails_ids);
```

### Upgrading from 3.x

Prior to 3.1, `Mailbox` used a "magic" method (`Mailbox::imap()`), with the
class `Imap` now performing it's purpose to call many `imap_*` functions with
automated string encoding/decoding of arguments and return values:

Before:

```php
    public function checkMailbox()
    {
        return $this->imap('check');
    }
```

After:

```php
    public function checkMailbox(): object
    {
        return Imap::check($this->getImapStream());
    }
```

### Recommended

* Google Chrome extension [PHP Console](https://chrome.google.com/webstore/detail/php-console/nfhmhhlpfleoednkpnnnkolmclajemef)
* Google Chrome extension [JavaScript Errors Notifier](https://chrome.google.com/webstore/detail/javascript-errors-notifie/jafmfknfnkoekkdocjiaipcnmkklaajd)
