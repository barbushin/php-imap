<?php

/**
* Mailbox - PHPUnit tests.
*
* @author    Sebastian Kraetzig <sebastian-kraetzig@gmx.de>
*/

use PhpImap\Mailbox;
use PhpImap\Exceptions\ConnectionException;
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

		$this->mailbox->setTimeouts(1, array(IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT));
		$this->expectException(ConnectionException::class);
		$this->mailbox->getImapStream();
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
	 * Test, that server encoding...
	 * - is set to a default value
	 * - only can use supported character encodings
	 * - that all functions uppers the server encoding setting
	*/
	public function testServerEncodingHasDefaultSettingAndOnlyUseSupportedSettings() {
		// Default character encoding should be set
		$mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir);
		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);

		// Only supported character encodings should be possible to use
		$test_character_encodings = array(
			// Supported encodings
			'1' => 'UTF-7',
			'1' => 'UTF7-IMAP',
			'1' => 'UTF-8',
			'1' => 'US-ASCII',
			'1' => 'ASCII',
			'1' => 'ISO-8859-1',
			'1' => 'ISO-8859-7',
			'1' => 'ISO-8859-11',
			'1' => 'ISO-8859-16',
			// NOT supported encodings
			'0' => 'UTF7',
			'0' => 'UTF-7-IMAP',
			'0' => 'UTF-7IMAP',
			'0' => 'UTF8',
			'0' => 'USASCII',
			'0' => 'ASC11',
			'0' => 'ISO-8859-0',
			'0' => 'ISO-8855-7',
			'0' => 'ISO-8859',
			'0' => 'ISO-8859-99',
		);

		foreach($test_character_encodings as $bool => $encoding) {
			if($bool) {
				$mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, $encoding);
				$this->assertAttributeEquals($encoding, 'serverEncoding', $mailbox);
			} else {
				$mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, $encoding);
				$this->assertAttributeNotEquals($encoding, 'serverEncoding', $mailbox);
			}
		}

		// Server encoding should be always upper formatted
		$mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, 'utf-8');
		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);

		$mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF7-IMAP');
		$mailbox->setServerEncoding('uTf-8');
		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);
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


	/**
	 * Test, different datetimes conversions using differents timezones
	 */

	public function testParsedDateDifferentTimeZones(){
		$test_datetimes = array (
			array('Sun, 14 Aug 2005 16:13:03 +0000 (CEST)' ,'1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +0000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +1000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +1000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -1000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +2000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +2000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -2000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +3000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +3000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -3000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +4000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +4000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -4000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +5000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +5000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -5000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +6000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +6000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -6000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +7000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +7000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -7000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +8000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +8000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -8000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +9000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +9000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -9000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +1000 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +1000','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -1000','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +1100 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +1100','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 -1100','1124035983'),

			array('Sun, 14 Aug 2005 16:13:03 +1200 (CEST)','1124035983'),
			array('Sun, 14 Aug 2005 16:13:03 +1200','1124035983'),
			
		);

		foreach($test_datetimes as $datetime) {
			$dateToParse = $datetime["0"];
			$epochToCompare = $datetime["1"];

			$parsedDt = $this->mailbox->parseDateTime($dateToParse);

			$parsedDateTime = new DateTime($parsedDt);

			$this->assertEquals($parsedDateTime->format('U'), $epochToCompare);

		}

	}


	/**
	 * Test, parsed datetime being emtpy the header date 
	 */
	public function testParsedDateTimeWithEmptyHeaderDate() {
		$parsedDt = $this->mailbox->parseDateTime('');
		$now = new DateTime;
		$this->assertEquals($parsedDt, $now->format('Y-m-d H:i:s'));

	}

	/**
	 * Test, that mime encoding returns correct strings
	 */
	public function testMimeEncoding() {
		$test_strings = array(
			'=?iso-8859-1?Q?Sebastian_Kr=E4tzig?= <sebastian.kraetzig@example.com>' => 'Sebastian Krätzig <sebastian.kraetzig@example.com>',
			'=?iso-8859-1?Q?Sebastian_Kr=E4tzig?=' => 'Sebastian Krätzig',
			'sebastian.kraetzig' => 'sebastian.kraetzig',
			'=?US-ASCII?Q?Keith_Moore?= <km@ab.example.edu>' => 'Keith Moore <km@ab.example.edu>',
			'   ' => '',
			'=?ISO-8859-1?Q?Max_J=F8rn_Simsen?= <max.joern.s@example.dk>' => 'Max Jørn Simsen <max.joern.s@example.dk>',
			'=?ISO-8859-1?Q?Andr=E9?= Muster <andre.muster@vm1.ulg.ac.be>' => 'André Muster <andre.muster@vm1.ulg.ac.be>',
			'=?ISO-8859-1?B?SWYgeW91IGNhbiByZWFkIHRoaXMgeW8=?= =?ISO-8859-2?B?dSB1bmRlcnN0YW5kIHRoZSBleGFtcGxlLg==?=' => 'If you can read this you understand the example.'
		);

		foreach($test_strings as $str => $expected) {
			if(empty($expected)) {
				$this->expectException(Exception::class);
				$this->mailbox->decodeMimeStr($str);
			} else {
				$this->assertEquals($this->mailbox->decodeMimeStr($str), $expected);
			}
		}
	}
}
