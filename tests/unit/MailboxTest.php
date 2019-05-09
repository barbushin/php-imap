<?php

/**
* Mailbox - PHPUnit tests.
*
* @author    Sebastian Kraetzig <sebastian-kraetzig@gmx.de>
*/

use DateTime;
use PhpImap\Mailbox;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
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
	 * Test, that server encoding...
	 * - is set to a default value
	 * - only can use supported character encodings
	 * - that all functions uppers the server encoding setting
	*/
	public function testServerEncodingHasDefaultSettingAndOnlyUseSupportedSettings() {
		// Default character encoding should be set
		$mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir);
		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);

		// Server encoding should be always upper formatted
		$mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, 'utf-8');
		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);

		$mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF7-IMAP');
		$mailbox->setServerEncoding('uTf-8');
		$this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);

		// Only supported character encodings should be possible to use
		$test_character_encodings = array(
			// Supported encodings
			array('1', 'UTF-7'),
			array('1', 'UTF7-IMAP'),
			array('1', 'UTF-8'),
			array('1', 'ASCII'),
			array('1', 'ISO-8859-1'),
			// NOT supported encodings
			array('0', 'UTF7'),
			array('0', 'UTF-7-IMAP'),
			array('0', 'UTF-7IMAP'),
			array('0', 'UTF8'),
			array('0', 'USASCII'),
			array('0', 'ASC11'),
			array('0', 'ISO-8859-0'),
			array('0', 'ISO-8855-1'),
			array('0', 'ISO-8859')
		);

		foreach($test_character_encodings as $testCase) {
			$bool = $testCase[0];
			$encoding = $testCase[1];


			if($bool) {
				$this->mailbox->setServerEncoding($encoding);
				$this->assertEquals($encoding, $this->mailbox->getServerEncoding());
			} else {
				$this->expectException(InvalidParameterException::class);
				$this->mailbox->setServerEncoding($encoding);
				$this->assertNotEquals($encoding, $this->mailbox->getServerEncoding());
			}
		}
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
	 * Test, that the IMAP search option has a default value
	 * 1 => SE_UID
	 * 2 => SE_FREE
	*/
	public function testImapSearchOptionHasADefault()
	{
		$this->assertEquals($this->mailbox->getImapSearchOption(), 1);
	}

	/*
	 * Test, that the IMAP search option can be changed
	 * 1 => SE_UID
	 * 2 => SE_FREE
	*/
	public function testSetAndGetImapSearchOption()
	{
		define('ANYTHING', 0);

		$this->mailbox->setImapSearchOption(SE_FREE);
		$this->assertEquals($this->mailbox->getImapSearchOption(), 2);

		$this->expectException(InvalidParameterException::class);
		$this->mailbox->setImapSearchOption("SE_FREE");

		$this->expectException(InvalidParameterException::class);
		$this->mailbox->setImapSearchOption(ANYTHING);

		$this->mailbox->setImapSearchOption(SE_UID);
		$this->assertEquals($this->mailbox->getImapSearchOption(), 1);
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
		$random_strings = str_split('0123456789abcdefghijklmnopqrstuvwxyz!\§$%&/()=#~*+,;.:<>|_');

		foreach($random_strings as $str) {
			if(in_array($str, $supported_delimiters)) {
				$this->assertTrue($this->mailbox->validatePathDelimiter($str));
			} else {
				$this->expectException(InvalidParameterException::class);
				$this->mailbox->setPathDelimiter($str);
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
	 * Test, that the attachments are not ignored by default
	*/
	public function testGetAttachmentsAreNotIgnoredByDefault()
	{
		$this->assertEquals($this->mailbox->getAttachmentsIgnore(), false);
	}

	/*
	 * Test, that attachments can be ignored and only valid values are accepted
	*/
	public function testSetAttachmentsIgnore()
	{
		$test_params = array(
			array("assertEquals", true),
			array("assertEquals", false),
			array("expectException", 1),
			array("expectException", 0),
			array("expectException", "something"),
			array("expectException", 2)
		);

		foreach($test_params as $param) {
			$assertTest = $param[0];
			$paramValue = $param[1];

			if($assertTest == "expectException") {
				$this->expectException(InvalidParameterException::class);
				$this->mailbox->setAttachmentsIgnore($paramValue);
			} else {
				$this->mailbox->setAttachmentsIgnore($paramValue);
				$this->$assertTest($this->mailbox->getAttachmentsIgnore(), $paramValue);
			}
		}
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
	public function testParsedDateDifferentTimeZones() {
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
	 * Test, different invalid / unparseable datetimes conversions
	*/
	public function testParsedDateWithUnparseableDateTime() {
		$test_unparseable_datetimes = array (
			array('14 Aug 2005 16:13:03 +1200 (CEST)','1124035983'),
			array('14 Aug 2005 16:13:03 +1200','1124035983'),
			array('14 Aug 2005 16:13:03 -0500','1124035983'),
		);

		foreach($test_unparseable_datetimes as $datetime) {
			$dateToParse = $datetime["0"];
			$epochToCompare = $datetime["1"];

			$parsedDt = $this->mailbox->parseDateTime($dateToParse);

			$parsedDateTime = new DateTime($parsedDt);

			$this->assertNotEquals($parsedDateTime->format('U'), $epochToCompare);
		}
	}

	/**
	 * Test, parsed datetime being emtpy the header date 
	 */
	public function testParsedDateTimeWithEmptyHeaderDate() {
		$this->expectException(InvalidParameterException::class);
		$this->mailbox->parseDateTime('');

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

	/**
	* Test, that only supported timeouts can be set
	*/
	public function testSetTimeouts()
	{
		$this->mailbox->setTimeouts(1, array(IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT));
		$this->expectException(ConnectionException::class);
		$this->mailbox->getImapStream();

		//$supported_types = array(IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT);
		$test_timeouts = array(
			array('assertTrue', array(true, 1, array(IMAP_OPENTIMEOUT))),
			array('assertTrue', array(true, 1, array(IMAP_READTIMEOUT))),
			array('assertTrue', array(true, 1, array(IMAP_WRITETIMEOUT))),
			array('assertTrue', array(true, 1, array(IMAP_CLOSEDTIMEOUT))),
		);

		foreach($test_timeouts as $testCase) {
			$assertMethod = $testCase[0];
			$timeout = $testCase[1];

			if($assertMethod == 'expectException') {
				$this->expectException($timeout[1]);
			}

			try {
				$this->mailbox->setTimeouts($timeout[2], $timeout[3]);
			} catch(InvalidParameterException $ex) {
				continue;
			}

			if($assertMethod == 'assertTrue') {
				$this->assertTrue($timeout[1]);
			}
		}

	}

	/*
	 * Test, that only supported and valid connection args can be set
	*/
	public function testSetConnectionArgs() {
		define("SOMETHING", "some value");

		/*
		 * OPTIONS
		*/
		$test_options = array(
			// Supported Options
			array('1', OP_READONLY),
			array('1', OP_ANONYMOUS),
			array('1', OP_HALFOPEN),
			array('1', CL_EXPUNGE),
			array('1', OP_DEBUG),
			array('1', OP_SHORTCACHE),
			array('1', OP_SILENT),
			array('1', OP_PROTOTYPE),
			array('1', OP_SECURE),
			// NOT Supported Options
			array('0', 'OP_READONLY'),
			array('0', 'OP_READONLY.'),
			array('0', OP_READONLY.OP_DEBUG),
			array('0', "OP_READONLY"),
			array('0', SOMETHING),
			array('0', "SOMETHING"),
			array('0', '*'),
		);

		foreach($test_options as $testCase) {
			$bool = $testCase[0];
			$option = $testCase[1];

			if($bool) {
				try {
					$this->mailbox->setConnectionArgs($option);
				} catch(InvalidParameterException $ex) {
					continue;
				}
				$this->assertTrue(true);
			} else {
				$this->expectException(InvalidParameterException::class);
				$this->mailbox->setConnectionArgs($option);
			}
		}

		/*
		 * RETRIES NUMBER
		*/
		$test_retriesNum = array(
			// Supported Retries
			array('1', 0),
			array('1', 1),
			array('1', 3),
			array('1', 12),
			// NOT Supported Retries
			array('0', -1),
			array('0', -3),
			array('0', -12),
			array('0', -99),
			array('0', "-1"),
			array('0', "1"),
			array('0', "one"),
			array('0', "any non-integer value")
		);

		foreach($test_retriesNum as $testCase) {
			$bool = $testCase[0];
			$retriesNum = $testCase[1];

			if($bool) {
				try {
					$this->mailbox->setConnectionArgs(OP_READONLY, $retriesNum);
				} catch(InvalidParameterException $ex) {
					continue;
				}
				$this->assertTrue(true);
			} else {
				$this->expectException(InvalidParameterException::class);
				$this->mailbox->setConnectionArgs(OP_READONLY, $retriesNum);
			}
		}

		/*
		 * PARAMS
		*/
		$test_params = array(
			// Supported Params
			array('1', array('DISABLE_AUTHENTICATOR' => 'GSSAPI')),
			// NOT Supported Params
			array('1', DISABLE_AUTHENTICATOR),
			array('0', 'DISABLE_AUTHENTICATOR'),
			array('0', SOMETHING),
			array('0', "SOMETHING"),
		);

		foreach($test_params as $testCase) {
			$bool = $testCase[0];
			$param = $testCase[1];

			if($bool) {
				try {
					$this->mailbox->setConnectionArgs(OP_READONLY, 3, $param);
				} catch(InvalidParameterException $ex) {
					continue;
				}
				$this->assertTrue(true);
			} else {
				$this->expectException(InvalidParameterException::class);
				$this->mailbox->setConnectionArgs(OP_READONLY, 3, $param);
			}
		}
	}

	/*
	 * Test, that decoding mime strings return unchanged / not broken strings
	*/
	public function testDecodeMimeStr() {
		$test_strings = array(
			array('<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>', '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>'),
			array('<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>', '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>'),
			array('<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>', '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>'),
			array('<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>', '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>'),
			array('=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=', 'Some subject here 😘'),
			array('=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=', 'mountainguan测试'),
		);

		foreach($test_strings as $test) {
			$str = $test[0];
			$expectedStr = $test[1];
			$serverEncoding = (isset($test[2])) ? $test[2] : 'utf-8';

			$this->mailbox->setServerEncoding($serverEncoding);

			$this->assertEquals($this->mailbox->decodeMimeStr($str, $this->mailbox->getServerEncoding()), $expectedStr);
		}
	}
}
