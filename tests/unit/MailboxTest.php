<?php

/**
 * Mailbox - PHPUnit tests.
 *
 * @author Sebastian Kraetzig <sebastian-kraetzig@gmx.de>
 */
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;
use PHPUnit\Framework\TestCase;

final class MailboxTest extends TestCase
{
    /**
     * Holds a PhpImap\Mailbox instance.
     *
     * @var Mailbox
     */
    private $mailbox;

    /**
     * Holds the imap path.
     *
     * @var string
     */
    private $imapPath = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';

    /**
     * Holds the imap username.
     *
     * @var string|email
     */
    private $login = 'php-imap@example.com';

    /**
     * Holds the imap user password.
     *
     * @var string
     */
    private $password = 'v3rY!53cEt&P4sSWöRd$';

    /**
     * Holds the relative name of the directory, where email attachments will be saved.
     *
     * @var string
     */
    private $attachmentsDir = '.';

    /**
     * Holds the server encoding setting.
     *
     * @var string
     */
    private $serverEncoding = 'UTF-8';

    /**
     * Run before each test is started.
     */
    public function setUp()
    {
        $this->mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, $this->serverEncoding);
    }

    /**
     * Test, that the constructor returns an instance of PhpImap\Mailbox::class.
     */
    public function testConstructor()
    {
        $this->assertInstanceOf(Mailbox::class, $this->mailbox);
    }

    /**
     * Test, that the constructor trims possible variables
     * Leading and ending spaces are not even possible in some variables.
     */
    public function testConstructorTrimsPossibleVariables()
    {
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

    /**
     * Test, that the server encoding can be set.
     */
    public function testSetAndGetServerEncoding()
    {
        $this->mailbox->setServerEncoding('UTF-8');

        $this->assertEquals($this->mailbox->getServerEncoding(), 'UTF-8');
    }

    /**
     * Test, that server encoding is set to a default value.
     */
    public function testServerEncodingHasDefaultSetting()
    {
        // Default character encoding should be set
        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir);
        $this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);
    }

    /**
     * Test, that server encoding that all functions uppers the server encoding setting.
     */
    public function testServerEncodingUppersSetting()
    {
        // Server encoding should be always upper formatted
        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, 'utf-8');
        $this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);

        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF7-IMAP');
        $mailbox->setServerEncoding('uTf-8');
        $this->assertAttributeEquals('UTF-8', 'serverEncoding', $mailbox);
    }

    /**
     * Provides test data for testing server encodings.
     */
    public function serverEncodingProvider()
    {
        return [
            // Supported encodings
            'UTF-7' => [true, 'UTF-7'],
            'UTF7-IMAP' => [true, 'UTF7-IMAP'],
            'UTF-8' => [true, 'UTF-8'],
            'ASCII' => [true, 'ASCII'],
            'ASCII' => [true, 'US-ASCII'],
            'ISO-8859-1' => [true, 'ISO-8859-1'],
            // NOT supported encodings
            'UTF7' => [false, 'UTF7'],
            'UTF-7-IMAP' => [false, 'UTF-7-IMAP'],
            'UTF-7IMAP' => [false, 'UTF-7IMAP'],
            'UTF8' => [false, 'UTF8'],
            'USASCII' => [false, 'USASCII'],
            'ASC11' => [false, 'ASC11'],
            'ISO-8859-0' => [false, 'ISO-8859-0'],
            'ISO-8855-1' => [false, 'ISO-8855-1'],
            'ISO-8859' => [false, 'ISO-8859'],
        ];
    }

    /**
     * Test, that server encoding only can use supported character encodings.
     *
     * @dataProvider serverEncodingProvider
     */
    public function testServerEncodingOnlyUseSupportedSettings($bool, $encoding)
    {
        if ($bool) {
            $this->mailbox->setServerEncoding($encoding);
            $this->assertEquals($encoding, $this->mailbox->getServerEncoding());
        } else {
            $this->expectException(InvalidParameterException::class);
            $this->mailbox->setServerEncoding($encoding);
            $this->assertNotEquals($encoding, $this->mailbox->getServerEncoding());
        }
    }

    /**
     * Test, that the IMAP search option has a default value
     * 1 => SE_UID
     * 2 => SE_FREE.
     */
    public function testImapSearchOptionHasADefault()
    {
        $this->assertEquals($this->mailbox->getImapSearchOption(), 1);
    }

    /**
     * Test, that the IMAP search option can be changed
     * 1 => SE_UID
     * 2 => SE_FREE.
     */
    public function testSetAndGetImapSearchOption()
    {
        define('ANYTHING', 0);

        $this->mailbox->setImapSearchOption(SE_FREE);
        $this->assertEquals($this->mailbox->getImapSearchOption(), 2);

        $this->expectException(InvalidParameterException::class);
        $this->mailbox->setImapSearchOption('SE_FREE');

        $this->expectException(InvalidParameterException::class);
        $this->mailbox->setImapSearchOption(ANYTHING);

        $this->mailbox->setImapSearchOption(SE_UID);
        $this->assertEquals($this->mailbox->getImapSearchOption(), 1);
    }

    /**
     * Test, that the imap login can be retrieved.
     */
    public function testGetLogin()
    {
        $this->assertEquals($this->mailbox->getLogin(), 'php-imap@example.com');
    }

    /**
     * Test, that the path delimiter has a default value.
     */
    public function testPathDelimiterHasADefault()
    {
        $this->assertNotEmpty($this->mailbox->getPathDelimiter());
    }

    /**
     * Provides test data for testing path delimiter.
     */
    public function pathDelimiterProvider()
    {
        return [
            '0' => ['0'],
            '1' => ['1'],
            '2' => ['2'],
            '3' => ['3'],
            '4' => ['4'],
            '5' => ['5'],
            '6' => ['6'],
            '7' => ['7'],
            '8' => ['8'],
            '9' => ['9'],
            'a' => ['a'],
            'b' => ['b'],
            'c' => ['c'],
            'd' => ['d'],
            'e' => ['e'],
            'f' => ['f'],
            'g' => ['g'],
            'h' => ['h'],
            'i' => ['i'],
            'j' => ['j'],
            'k' => ['k'],
            'l' => ['l'],
            'm' => ['m'],
            'n' => ['n'],
            'o' => ['o'],
            'p' => ['p'],
            'q' => ['q'],
            'r' => ['r'],
            's' => ['s'],
            't' => ['t'],
            'u' => ['u'],
            'v' => ['v'],
            'w' => ['w'],
            'x' => ['x'],
            'y' => ['y'],
            'z' => ['z'],
            '!' => ['!'],
            '\\' => ['\\'],
            '$' => ['$'],
            '%' => ['%'],
            '§' => ['§'],
            '&' => ['&'],
            '/' => ['/'],
            '(' => ['('],
            ')' => [')'],
            '=' => ['='],
            '#' => ['#'],
            '~' => ['~'],
            '*' => ['*'],
            '+' => ['+'],
            ',' => [','],
            ';' => [';'],
            '.' => ['.'],
            ':' => [':'],
            '<' => ['<'],
            '>' => ['>'],
            '|' => ['|'],
            '_' => ['_'],
        ];
    }

    /**
     * Test, that the path delimiter is checked for supported chars.
     *
     * @dataProvider pathDelimiterProvider
     */
    public function testPathDelimiterIsBeingChecked($str)
    {
        $supported_delimiters = ['.', '/'];

        if (in_array($str, $supported_delimiters)) {
            $this->assertTrue($this->mailbox->validatePathDelimiter($str));
        } else {
            $this->expectException(InvalidParameterException::class);
            $this->mailbox->setPathDelimiter($str);
        }
    }

    /**
     * Test, that the path delimiter can be set.
     */
    public function testSetAndGetPathDelimiter()
    {
        $this->mailbox->setPathDelimiter('.');
        $this->assertEquals($this->mailbox->getPathDelimiter(), '.');

        $this->mailbox->setPathDelimiter('/');
        $this->assertEquals($this->mailbox->getPathDelimiter(), '/');
    }

    /**
     * Test, that the attachments are not ignored by default.
     */
    public function testGetAttachmentsAreNotIgnoredByDefault()
    {
        $this->assertEquals($this->mailbox->getAttachmentsIgnore(), false);
    }

    /**
     * Provides test data for testing attachments ignore.
     */
    public function attachmentsIgnoreProvider()
    {
        return [
            'true' => ['assertEquals', true],
            'false' => ['assertEquals', false],
            '1' => ['expectException', 1],
            '0' => ['expectException', 0],
            'something' => ['expectException', 'something'],
            '2' => ['expectException', 2],
        ];
    }

    /**
     * Test, that attachments can be ignored and only valid values are accepted.
     *
     * @dataProvider attachmentsIgnoreProvider
     */
    public function testSetAttachmentsIgnore($assertTest, $paramValue)
    {
        if ('expectException' == $assertTest) {
            $this->expectException(InvalidParameterException::class);
            $this->mailbox->setAttachmentsIgnore($paramValue);
        } else {
            $this->mailbox->setAttachmentsIgnore($paramValue);
            $this->$assertTest($this->mailbox->getAttachmentsIgnore(), $paramValue);
        }
    }

    /**
     * Provides test data for testing encoding.
     */
    public function encodingTestStringsProvider()
    {
        return [
            'Avañe’ẽ' => ['Avañe’ẽ'], // Guaraní
            'azərbaycanca' => ['azərbaycanca'], // Azerbaijani (Latin)
            'Bokmål' => ['Bokmål'], // Norwegian Bokmål
            'chiCheŵa' => ['chiCheŵa'], // Chewa
            'Deutsch' => ['Deutsch'], // German
            'U.S. English' => ['U.S. English'], // U.S. English
            'français' => ['français'], // French
            'føroyskt' => ['føroyskt'], // Faroese
            'Kĩmĩrũ' => ['Kĩmĩrũ'], // Kimîîru
            'Kɨlaangi' => ['Kɨlaangi'], // Langi
            'oʼzbekcha' => ['oʼzbekcha'], // Uzbek (Latin)
            'Plattdüütsch' => ['Plattdüütsch'], // Low German
            'română' => ['română'], // Romanian
            'Sängö' => ['Sängö'], // Sango
            'Tiếng Việt' => ['Tiếng Việt'], // Vietnamese
            'ɔl-Maa' => ['ɔl-Maa'], // Masai
            'Ελληνικά' => ['Ελληνικά'], // Greek
            'Ўзбек' => ['Ўзбек'], // Uzbek (Cyrillic)
            'Ўзбек' => ['Азәрбајҹан'], // Azerbaijani (Cyrillic)
            'Српски' => ['Српски'], // Serbian (Cyrillic)
            'русский' => ['русский'], // Russian
            'ѩзыкъ словѣньскъ' => ['ѩзыкъ словѣньскъ'], // Church Slavic
            'العربية' => ['العربية'], // Arabic
            'नेपाली' => ['नेपाली'], // Nepali
            '日本語' => ['日本語'], // Japanese
            '简体中文' => ['简体中文'], // Chinese (Simplified)
            '繁體中文' => ['繁體中文'], // Chinese (Traditional)
            '한국어' => ['한국어'], // Korean
            'ąčęėįšųūžĄČĘĖĮŠŲŪŽ' => ['ąčęėįšųūžĄČĘĖĮŠŲŪŽ'], // Lithuanian letters
        ];
    }

    /**
     * Test, that strings encoded to UTF-7 can be decoded back to UTF-8.
     *
     * @dataProvider encodingTestStringsProvider
     */
    public function testEncodingToUtf7DecodeBackToUtf8($str)
    {
        $utf7_encoded_str = $this->mailbox->encodeStringToUtf7Imap($str);
        $utf8_decoded_str = $this->mailbox->decodeStringFromUtf7ImapToUtf8($utf7_encoded_str);

        $this->assertEquals($utf8_decoded_str, $str);
    }

    /**
     * Test, that strings encoded to UTF-7 can be decoded back to UTF-8.
     *
     * @dataProvider encodingTestStringsProvider
     */
    public function testMimeDecodingReturnsCorrectValues($str)
    {
        $this->assertEquals($this->mailbox->decodeMimeStr($str, 'utf-8'), $str);
    }

    /**
     * Provides test data for testing parsing datetimes.
     */
    public function datetimeProvider()
    {
        return [
            'Sun, 14 Aug 2005 16:13:03 +0000 (CEST)' => ['2005-08-14T16:13:03+00:00', '1124035983'],
            'Sun, 14 Aug 2005 16:13:03 +0000' => ['2005-08-14T16:13:03+00:00', '1124035983'],

            'Sun, 14 Aug 2005 16:13:03 +1000 (CEST)' => ['2005-08-14T06:13:03+00:00', '1123999983'],
            'Sun, 14 Aug 2005 16:13:03 +1000' => ['2005-08-14T06:13:03+00:00', '1123999983'],
            'Sun, 14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', '1124071983'],

            'Sun, 14 Aug 2005 16:13:03 +1000 (CEST)' => ['2005-08-14T06:13:03+00:00', '1123999983'],
            'Sun, 14 Aug 2005 16:13:03 +1000' => ['2005-08-14T06:13:03+00:00', '1123999983'],
            'Sun, 14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', '1124071983'],

            'Sun, 14 Aug 2005 16:13:03 +1100 (CEST)' => ['2005-08-14T05:13:03+00:00', '1123996383'],
            'Sun, 14 Aug 2005 16:13:03 +1100' => ['2005-08-14T05:13:03+00:00', '1123996383'],
            'Sun, 14 Aug 2005 16:13:03 -1100' => ['2005-08-15T03:13:03+00:00', '1124075583'],

            '14 Aug 2005 16:13:03 +1000 (CEST)' => ['2005-08-14T06:13:03+00:00', '1123999983'],
            '14 Aug 2005 16:13:03 +1000' => ['2005-08-14T06:13:03+00:00', '1123999983'],
            '14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', '1124071983'],
        ];
    }

    /**
     * Test, different datetimes conversions using differents timezones.
     *
     * @dataProvider datetimeProvider
     */
    public function testParsedDateDifferentTimeZones($dateToParse, $epochToCompare)
    {
        $parsedDt = $this->mailbox->parseDateTime($dateToParse);
        $parsedDateTime = new DateTime($parsedDt);
        $this->assertEquals($parsedDateTime->format('U'), $epochToCompare);
    }

    /**
     * Provides test data for testing parsing invalid / unparseable datetimes.
     */
    public function invalidDatetimeProvider()
    {
        return [
            'Sun, 14 Aug 2005 16:13:03 +9000 (CEST)' => ['Sun, 14 Aug 2005 16:13:03 +9000 (CEST)'],
            'Sun, 14 Aug 2005 16:13:03 +9000' => ['Sun, 14 Aug 2005 16:13:03 +9000'],
            'Sun, 14 Aug 2005 16:13:03 -9000' => ['Sun, 14 Aug 2005 16:13:03 -9000'],
        ];
    }

    /**
     * Test, different invalid / unparseable datetimes conversions.
     *
     * @dataProvider invalidDatetimeProvider
     */
    public function testParsedDateWithUnparseableDateTime($dateToParse)
    {
        $parsedDt = $this->mailbox->parseDateTime($dateToParse);
        $this->assertEquals($parsedDt, $dateToParse);
    }

    /**
     * Test, parsed datetime being emtpy the header date.
     */
    public function testParsedDateTimeWithEmptyHeaderDate()
    {
        $this->expectException(InvalidParameterException::class);
        $this->mailbox->parseDateTime('');
    }

    /**
     * Provides test data for testing mime encoding.
     */
    public function mimeEncodingProvider()
    {
        return [
            ['=?iso-8859-1?Q?Sebastian_Kr=E4tzig?= <sebastian.kraetzig@example.com>', 'Sebastian Krätzig <sebastian.kraetzig@example.com>'],
            ['=?iso-8859-1?Q?Sebastian_Kr=E4tzig?=', 'Sebastian Krätzig'],
            ['sebastian.kraetzig', 'sebastian.kraetzig'],
            ['=?US-ASCII?Q?Keith_Moore?= <km@ab.example.edu>', 'Keith Moore <km@ab.example.edu>'],
            ['   ', ''],
            ['=?ISO-8859-1?Q?Max_J=F8rn_Simsen?= <max.joern.s@example.dk>', 'Max Jørn Simsen <max.joern.s@example.dk>'],
            ['=?ISO-8859-1?Q?Andr=E9?= Muster <andre.muster@vm1.ulg.ac.be>', 'André Muster <andre.muster@vm1.ulg.ac.be>'],
            ['=?ISO-8859-1?B?SWYgeW91IGNhbiByZWFkIHRoaXMgeW8=?= =?ISO-8859-2?B?dSB1bmRlcnN0YW5kIHRoZSBleGFtcGxlLg==?=', 'If you can read this you understand the example.'],
        ];
    }

    /**
     * Test, that mime encoding returns correct strings.
     *
     * @dataProvider mimeEncodingProvider
     */
    public function testMimeEncoding($str, $expected)
    {
        if (empty($expected)) {
            $this->expectException(Exception::class);
            $this->mailbox->decodeMimeStr($str);
        } else {
            $this->assertEquals($this->mailbox->decodeMimeStr($str), $expected);
        }
    }

    /**
     * Provides test data for testing timeouts.
     */
    public function timeoutsProvider()
    {
        return [
            'array(IMAP_OPENTIMEOUT)' => ['assertNull', 1, [IMAP_OPENTIMEOUT]],
            'array(IMAP_READTIMEOUT)' => ['assertNull', 1, [IMAP_READTIMEOUT]],
            'array(IMAP_WRITETIMEOUT)' => ['assertNull', 1, [IMAP_WRITETIMEOUT]],
            'array(IMAP_CLOSETIMEOUT)' => ['assertNull', 1, [IMAP_CLOSETIMEOUT]],
            'array(IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT)' => ['assertNull', 1, [IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT]],
            'array(IMAP_OPENTIMEOUT)' => ['expectException', 1, [OPENTIMEOUT]],
            'array(IMAP_READTIMEOUT)' => ['expectException', 1, [READTIMEOUT]],
            'array(IMAP_WRITETIMEOUT)' => ['expectException', 1, [WRITETIMEOUT]],
            'array(IMAP_CLOSETIMEOUT)' => ['expectException', 1, [CLOSETIMEOUT]],
            'array(IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT)' => ['expectException', 1, [IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, WRITETIMEOUT, IMAP_CLOSETIMEOUT]],
        ];
    }

    /**
     * Test, that only supported timeouts can be set.
     *
     * @dataProvider timeoutsProvider
     */
    public function testSetTimeouts($assertMethod, $timeout, $types)
    {
        if ('expectException' == $assertMethod) {
            $this->expectException(InvalidParameterException::class);
            $this->mailbox->setTimeouts($timeout, $types);
        } elseif ('assertNull' == $assertMethod) {
            $this->assertNull($this->mailbox->setTimeouts($timeout, $types));
        }
    }

    /**
     * Provides test data for testing connection args.
     */
    public function connectionArgsProvider()
    {
        return [
            ['assertNull', OP_READONLY, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_READONLY, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_ANONYMOUS, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_HALFOPEN, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', CL_EXPUNGE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_DEBUG, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_SHORTCACHE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_SILENT, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_PROTOTYPE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_SECURE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_READONLY, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_READONLY, 3, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['assertNull', OP_READONLY, 12, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],

            ['expectException', OP_READONLY.OP_DEBUG, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, -1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, -3, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, -12, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, '-1', ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, '1', ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, 0, DISABLE_AUTHENTICATOR],
            ['expectException', OP_READONLY, 0, 'DISABLE_AUTHENTICATOR'],
            ['expectException', OP_READONLY, 0, SOMETHING],
            ['expectException', OP_READONLY, 0, 'SOMETHING'],
        ];
    }

    /**
     * Test, that only supported and valid connection args can be set.
     *
     * @dataProvider connectionArgsProvider
     */
    public function testSetConnectionArgs($assertMethod, $option, $retriesNum, $param)
    {
        if ('expectException' == $assertMethod) {
            $this->expectException(InvalidParameterException::class);
            $this->mailbox->setConnectionArgs($option, $retriesNum, $param);
        } elseif ('assertNull' == $assertMethod) {
            $this->assertNull($this->mailbox->setConnectionArgs($option, $retriesNum, $param));
        }
    }

    /**
     * Provides test data for testing mime string decoding.
     */
    public function mimeStrDecodingProvider()
    {
        return [
            '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>' => ['<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>', '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>'],
            '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>' => ['<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>', '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>'],
            '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>' => ['<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>', '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>'],
            '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>' => ['<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>', '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>'],
            'Some subject here 😘' => ['=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=', 'Some subject here 😘'],
            'mountainguan测试' => ['=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=', 'mountainguan测试'],
            "This is the Euro symbol ''." => ["This is the Euro symbol ''.", "This is the Euro symbol ''."],
            'Some subject here 😘' => ['=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=', 'Some subject here 😘', 'US-ASCII'],
            'mountainguan测试' => ['=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=', 'mountainguan测试', 'US-ASCII'],
            'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something' => ['مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something', 'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something'],
        ];
    }

    /**
     * Test, that decoding mime strings return unchanged / not broken strings.
     *
     * @dataProvider mimeStrDecodingProvider
     */
    public function testDecodeMimeStr($str, $expectedStr, $serverEncoding = 'utf-8')
    {
        $this->mailbox->setServerEncoding($serverEncoding);
        $this->assertEquals($this->mailbox->decodeMimeStr($str, $this->mailbox->getServerEncoding()), $expectedStr);
    }
}
