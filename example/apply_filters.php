<?php
require_once('../src/ImapMailbox.php');
require_once('../src/MailFilters.php');

// IMAP must be enabled in Google Mail Settings
define('GMAIL_EMAIL', 'some@gmail.com');
define('GMAIL_PASSWORD', '*********');
define('ATTACHMENTS_DIR', dirname(__FILE__) . '/attachments');

$mailbox = new ImapMailbox('{imap.gmail.com:993/imap/ssl}INBOX', GMAIL_EMAIL, GMAIL_PASSWORD, ATTACHMENTS_DIR, 'utf-8');
$mails = array();

// Get some mails
$mailsIds = $mailbox->searchMailBox('ALL');

if(!$mailsIds) {
	die('Mailbox is empty');
}
$mailFilters = new MailFilters($mailbox);

$mailFilters->enableDebug(); //Should be removed in production environment

/** 
* ===========================================================
* ===============  Add Few Filters Here  ====================
* ===========================================================
*/
		//Add a filter to check if the message was sent to "mail1@gmail.com" OR "mail2@gmail.com" move it to "New Mail Folder" mailbox
		$mailFilters->addFilter('to',array("mail1@gmail.com","mail2@gmail.com"),array("do"=>"move","params"=>"New Mail Folder"));
		
		//Add a Filter so that if the subject has the words "HasSomethingInIt" return me an array with an element saying "Hello World, the Subject filter was matched"
		$mailFilters->addFilter('subject',"*RID*",array("do"=>"return","params"=>array("Hello World, the Subject filter was matched")));
		
		//Add a Filter that will delete all messages send from "mail1@gmail.com"
		$mailFilters->addFilter('from',"mail1@gmail.com",array("do"=>"delete"));

/*
* ===============+++  End Add Filters  +++====================
*/

//Loop through all fetched messages and apply the filters to it
foreach($mailsIds as $i=>$uid){
	$mail = $mailbox->getMail($uid);
	
	//This variable ($appliedFiltersVars) will hold the return params incase the user provided any
	$appliedFiltersVars = $mailFilters->applyFilters($mail);
	var_dump($appliedFiltersVars);
	
	//Limit to the first 6 messages for the purpose of the example only
	if($i > 5){
		exit;
	}
}