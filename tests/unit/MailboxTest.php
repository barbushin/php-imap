<?php

use PHPUnit\Framework\TestCase;

final class MailboxTest extends TestCase
{
	protected $mailbox;
	protected $imapPath = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';
	protected $login = 'php-imap@example.com';
	protected $password = 'v3rY!53cEt&P4sSWöRd$';
	protected $attachmentsDir = '.';
	protected $serverEncoding = 'UTF-8';

	public function setUp() {

		$this->mailbox = new PhpImap\Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, $this->serverEncoding);
	}

	public function testConstructor()
	{
		$this->assertInstanceOf(PhpImap\Mailbox::class, $this->mailbox);
	}

	public function testConstructorTrimsPossibleVariables() {
		$imapPath = ' {imap.example.com:993/imap/ssl}INBOX     ';
		$login = '    php-imap@example.com';
		$password = '  v3rY!53cEt&P4sSWöRd$';
		// directory names can contain spaces before AND after on Linux/Unix systems. Windows trims these spaces automatically.
		$attachmentsDir = '.';
		$serverEncoding = 'UTF-8  ';

		$mailbox = new PhpImap\Mailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

		$this->assertAttributeEquals('{imap.example.com:993/imap/ssl}INBOX', 'imapPath', $mailbox);
		$this->assertAttributeEquals('php-imap@example.com', 'imapLogin', $mailbox);
		$this->assertAttributeEquals('  v3rY!53cEt&P4sSWöRd$', 'imapPassword', $mailbox);
		$this->assertAttributeEquals(realpath('.'), 'attachmentsDir', $mailbox);
		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);
	}

	public function testConstructorUppersServerEncoding() {
		$serverEncoding = 'Utf-8';

		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $this->mailbox);
	}

	public function testSetAndGetServerEncoding()
	{
		$this->mailbox->setServerEncoding('UTF-8');

		$this->assertEquals($this->mailbox->getServerEncoding(), 'UTF-8');
	}

	public function testGetLogin()
	{
		$this->assertEquals($this->mailbox->getLogin(), 'php-imap@example.com');
	}
}
