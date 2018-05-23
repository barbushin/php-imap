# PHP IMAP

[![Author](http://img.shields.io/badge/author-@barbushin-blue.svg?style=flat-square)](https://www.linkedin.com/in/barbushin)
[![GitHub release](https://img.shields.io/github/release/barbushin/php-imap.svg?maxAge=86400&style=flat-square)](https://packagist.org/packages/php-imap/php-imap)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/php-imap/php-imap.svg?maxAge=86400&style=flat-square)](https://packagist.org/packages/php-imap/php-imap)

### Features

* Connect to mailbox by POP3/IMAP/NNTP, using [PHP IMAP extension](http://php.net/manual/book.imap.php)
* Get emails with attachments and inline images
* Get emails filtered or sorted by custom criteria
* Mark emails as seen/unseen
* Delete emails
* Manage mailbox folders
 
### Requirements

* IMAP extension must be present; so make sure this line is active in your php.ini: `extension=php_imap.dll`

### Installation by Composer

	$ composer require php-imap/php-imap
	
### Integration with frameworks

* Symfony - https://github.com/secit-pl/imap-bundle

### Usage example

```php
$mailbox = new PhpImap\Mailbox(
	'{imap.gmail.com:993/imap/ssl}INBOX', 
	'some@gmail.com', 
	'*********', 
	__DIR__ // The directory into which attachments are to be saved. Ain't saved if FALSE.
); 
```

```php
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

Method imap() allows to call any imap function in a context of the the instance

```php
// Call imap_check(); 	
// http://php.net/manual/en/function.imap-check.php	
$info = $mailbox->imap('check'); // 
	
// Show current time for the mailbox
$currentServerTime = isset($info->Date) && $info->Date ? date('Y-m-d H:i:s', strtotime($info->Date)) : 'Unknown';	
	
echo $currentServerTime;
```

Some request require much time and resources:

```php
$mailbox->setAttachmentsIgnore(true); // If you don't need to grab attachments you can significantly increase performance of your application

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

### Recommended

* Google Chrome extension [PHP Console](https://chrome.google.com/webstore/detail/php-console/nfhmhhlpfleoednkpnnnkolmclajemef)
* Google Chrome extension [JavaScript Errors Notifier](https://chrome.google.com/webstore/detail/javascript-errors-notifie/jafmfknfnkoekkdocjiaipcnmkklaajd)
