<?php

/**
* Mailbox - PHPUnit tests.
*
* @author    Sebastian Kraetzig <sebastian-kraetzig@gmx.de>
*/

use PhpImap\Mailbox;
use PHPUnit\Framework\TestCase;

final class MailboxTest extends TestCase
{
	/**
	* Holds a PhpImap\Mailbox instance
	*
	* @var Mailbox
	*/
	private $mailbox;

	/**
	* Holds the imap path
	*
	* @var string
	*/
	private $imapPath = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';

	/**
	* Holds the imap username
	*
	* @var string|email
	*/
	private $login = 'php-imap@example.com';

	/**
	* Holds the imap user password
	*
	* @var string
	*/
	private $password = 'v3rY!53cEt&P4sSWöRd$';

	/**
	* Holds the relative name of the directory, where email attachments will be saved
	*
	* @var string
	*/
	private $attachmentsDir = '.';

	/**
	* Holds the server encoding setting
	*
	* @var string
	*/
	private $serverEncoding = 'UTF-8';

	/**
	* Run before each test is started.
	*/
	public function setUp() {

		$this->mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, $this->serverEncoding);
	}

	/**
	* Test, that the constructor returns an instance of PhpImap\Mailbox::class
	*/
	public function testConstructor()
	{
		$this->assertInstanceOf(Mailbox::class, $this->mailbox);
	}

	/*
	 * Test, that the constructor trims possible variables
	 * Leading and ending spaces are not even possible in some variables.
	*/
	public function testConstructorTrimsPossibleVariables() {
		$imapPath = ' {imap.example.com:993/imap/ssl}INBOX     ';
		$login = '    php-imap@example.com';
		$password = '  v3rY!53cEt&P4sSWöRd$';
		// directory names can contain spaces before AND after on Linux/Unix systems. Windows trims these spaces automatically.
		$attachmentsDir = '.';
		$serverEncoding = 'UTF-8  ';

		$mailbox = new Mailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

		$this->assertAttributeEquals('{imap.example.com:993/imap/ssl}INBOX', 'imapPath', $mailbox);
		$this->assertAttributeEquals('php-imap@example.com', 'imapLogin', $mailbox);
		$this->assertAttributeEquals('  v3rY!53cEt&P4sSWöRd$', 'imapPassword', $mailbox);
		$this->assertAttributeEquals(realpath('.'), 'attachmentsDir', $mailbox);
		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);
	}

	/*
	 * Test, that the constructor uppers the server encoding setting
	*/
	public function testConstructorUppersServerEncoding() {
		$serverEncoding = 'Utf-8';

		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $this->mailbox);
	}

	/*
	 * Test, that the server encoding can be set
	*/
	public function testSetAndGetServerEncoding()
	{
		$this->mailbox->setServerEncoding('UTF-8');

		$this->assertEquals($this->mailbox->getServerEncoding(), 'UTF-8');
	}

	/*
	 * Test, that the imap login can be retrieved
	*/
	public function testGetLogin()
	{
		$this->assertEquals($this->mailbox->getLogin(), 'php-imap@example.com');
	}

	/*
	 * Test, that the path delimiter has a default value
	*/
	public function testPathDelimiterHasADefault()
	{
		$this->assertNotEmpty($this->mailbox->getPathDelimiter());
	}

	/*
	 * Test, that the path delimiter is checked for supported chars
	*/
	public function testPathDelimiterIsBeingChecked()
	{
		$supported_delimiters = array('.', '/');
		$random_strings = str_split(substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz!'§$%&/()=#~*+,;.:<>|"), 0));

		foreach($random_strings as $str) {
			$this->mailbox->setPathDelimiter($str);

			if(in_array($str, $supported_delimiters)) {
				$this->assertTrue($this->mailbox->validatePathDelimiter());
			} else {
				$this->assertFalse($this->mailbox->validatePathDelimiter());
			}
		}
	}

	/*
	 * Test, that the path delimiter can be set
	*/
	public function testSetAndGetPathDelimiter()
	{
		$this->mailbox->setPathDelimiter('.');
		$this->assertEquals($this->mailbox->getPathDelimiter(), '.');

		$this->mailbox->setPathDelimiter('/');
		$this->assertEquals($this->mailbox->getPathDelimiter(), '/');
	}

	/*
	 * Test, that values are identical before and after encoding
	*/
	public function testEncodingReturnsCorrectValues()
	{
		$test_strings = array(
			'Avañe’ẽ', // Guaraní
			'azərbaycanca', // Azerbaijani (Latin)
			'Bokmål', // Norwegian Bokmål
			'chiCheŵa', // Chewa
			'Deutsch', // German
			'U.S. English', // U.S. English
			'français', // French
			'føroyskt', // Faroese
			'Kĩmĩrũ', // Kimîîru
			'Kɨlaangi', // Langi
			'oʼzbekcha', // Uzbek (Latin)
			'Plattdüütsch', // Low German
			'română', // Romanian
			'Sängö', // Sango
			'Tiếng Việt', // Vietnamese
			'ɔl-Maa', // Masai
			'Ελληνικά', // Greek
			'Ўзбек', // Uzbek (Cyrillic)
			'Азәрбајҹан', // Azerbaijani (Cyrillic)
			'Српски', // Serbian (Cyrillic)
			'русский', // Russian
			'ѩзыкъ словѣньскъ', // Church Slavic
			'العربية', // Arabic
			'नेपाली', // / Nepali
			'日本語', // Japanese
			'简体中文', // Chinese (Simplified)
			'繁體中文', // Chinese (Traditional)
			'한국어', // Korean
		);

		foreach($test_strings as $str) {
			$utf7_encoded_str = $this->mailbox->encodeStringToUtf7Imap($str);
			$utf8_decoded_str = $this->mailbox->decodeStringFromUtf7ImapToUtf8($utf7_encoded_str);

			$this->assertEquals($utf8_decoded_str, $str);
		}
	}
}
