<?php
/**
 * Mailbox - PHPUnit tests.
 *
 * @author Sebastian Kraetzig <sebastian-kraetzig@gmx.de>
 */
declare(strict_types=1);

namespace PhpImap;

use DateTime;
use Exception;
use PhpImap\Exceptions\InvalidParameterException;
use PHPUnit\Framework\TestCase;

final class MailboxTest extends TestCase
{
    const ANYTHING = 0;

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
     *
     * @psalm-var string
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
     * Test, that the constructor trims possible variables
     * Leading and ending spaces are not even possible in some variables.
     */
    public function testConstructorTrimsPossibleVariables(): void
    {
        $imapPath = ' {imap.example.com:993/imap/ssl}INBOX     ';
        $login = '    php-imap@example.com';
        $password = '  v3rY!53cEt&P4sSWöRd$';
        // directory names can contain spaces before AND after on Linux/Unix systems. Windows trims these spaces automatically.
        $attachmentsDir = '.';
        $serverEncoding = 'UTF-8  ';

        $mailbox = new Fixtures\Mailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $this->assertSame('{imap.example.com:993/imap/ssl}INBOX', $mailbox->getImapPath());
        $this->assertSame('php-imap@example.com', $mailbox->getLogin());
        $this->assertSame('  v3rY!53cEt&P4sSWöRd$', $mailbox->getImapPassword());
        $this->assertSame(\realpath('.'), $mailbox->getAttachmentsDir());
        $this->assertSame('UTF-8', $mailbox->getServerEncoding());
    }

    /**
     * @psalm-return list<array{0:string}>
     */
    public function SetAndGetServerEncodingProvider()
    {
        $data = [
            ['UTF-8'],
        ];

        $supported = \mb_list_encodings();

        foreach (
            [
                'Windows-1251',
                'Windows-1252',
            ] as $perhaps
        ) {
            if (
                \in_array(\trim($perhaps), $supported, true) ||
                \in_array(\strtoupper(\trim($perhaps)), $supported, true)
            ) {
                $data[] = [$perhaps];
            }
        }

        return $data;
    }

    /**
     * Test, that the server encoding can be set.
     *
     * @dataProvider SetAndGetServerEncodingProvider
     */
    public function testSetAndGetServerEncoding(string $encoding): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setServerEncoding($encoding);

        $encoding = \strtoupper(\trim($encoding));

        $this->assertEquals($mailbox->getServerEncoding(), $encoding);
    }

    /**
     * Test, that server encoding is set to a default value.
     */
    public function testServerEncodingHasDefaultSetting(): void
    {
        // Default character encoding should be set
        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir);
        $this->assertSame('UTF-8', $mailbox->getServerEncoding());
    }

    /**
     * Test, that server encoding that all functions uppers the server encoding setting.
     */
    public function testServerEncodingUppersSetting(): void
    {
        // Server encoding should be always upper formatted
        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, 'utf-8');
        $this->assertSame('UTF-8', $mailbox->getServerEncoding());

        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF7-IMAP');
        $mailbox->setServerEncoding('uTf-8');
        $this->assertSame('UTF-8', $mailbox->getServerEncoding());
    }

    /**
     * Provides test data for testing server encodings.
     *
     * @return array<string, array{0:bool, 1:string}>
     */
    public function serverEncodingProvider(): array
    {
        return [
            // Supported encodings
            'UTF-7' => [true, 'UTF-7'],
            'UTF7-IMAP' => [true, 'UTF7-IMAP'],
            'UTF-8' => [true, 'UTF-8'],
            'ASCII' => [true, 'ASCII'],
            'US-ASCII' => [true, 'US-ASCII'],
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
    public function testServerEncodingOnlyUseSupportedSettings(bool $bool, string $encoding): void
    {
        $mailbox = $this->getMailbox();

        if ($bool) {
            $mailbox->setServerEncoding($encoding);
            $this->assertEquals($encoding, $mailbox->getServerEncoding());
        } else {
            $this->expectException(InvalidParameterException::class);
            $mailbox->setServerEncoding($encoding);
            $this->assertNotEquals($encoding, $mailbox->getServerEncoding());
        }
    }

    /**
     * Test, that the IMAP search option has a default value
     * 1 => SE_UID
     * 2 => SE_FREE.
     */
    public function testImapSearchOptionHasADefault(): void
    {
        $this->assertEquals($this->getMailbox()->getImapSearchOption(), 1);
    }

    /**
     * Test, that the IMAP search option can be changed
     * 1 => SE_UID
     * 2 => SE_FREE.
     */
    public function testSetAndGetImapSearchOption(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setImapSearchOption(SE_FREE);
        $this->assertEquals($mailbox->getImapSearchOption(), 2);

        $this->expectException(InvalidParameterException::class);
        $mailbox->setImapSearchOption(self::ANYTHING);

        $mailbox->setImapSearchOption(SE_UID);
        $this->assertEquals($mailbox->getImapSearchOption(), 1);
    }

    /**
     * Test, that the imap login can be retrieved.
     */
    public function testGetLogin(): void
    {
        $this->assertEquals($this->getMailbox()->getLogin(), 'php-imap@example.com');
    }

    /**
     * Test, that the path delimiter has a default value.
     */
    public function testPathDelimiterHasADefault(): void
    {
        $this->assertNotEmpty($this->getMailbox()->getPathDelimiter());
    }

    /**
     * Provides test data for testing path delimiter.
     *
     * @psalm-return array{0:string}[]
     */
    public function pathDelimiterProvider(): array
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
    public function testPathDelimiterIsBeingChecked(string $str): void
    {
        $supported_delimiters = ['.', '/'];

        $mailbox = $this->getMailbox();

        if (\in_array($str, $supported_delimiters)) {
            $this->assertTrue($mailbox->validatePathDelimiter($str));
        } else {
            $this->expectException(InvalidParameterException::class);
            $mailbox->setPathDelimiter($str);
        }
    }

    /**
     * Test, that the path delimiter can be set.
     */
    public function testSetAndGetPathDelimiter(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setPathDelimiter('.');
        $this->assertEquals($mailbox->getPathDelimiter(), '.');

        $mailbox->setPathDelimiter('/');
        $this->assertEquals($mailbox->getPathDelimiter(), '/');
    }

    /**
     * Test, that the attachments are not ignored by default.
     */
    public function testGetAttachmentsAreNotIgnoredByDefault(): void
    {
        $this->assertEquals($this->getMailbox()->getAttachmentsIgnore(), false);
    }

    /**
     * Provides test data for testing attachments ignore.
     *
     * @psalm-return array<string, array{0:bool}>
     */
    public function attachmentsIgnoreProvider(): array
    {
        /** @psalm-var array<string, array{0:bool}> */
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    /**
     * Test, that attachments can be ignored and only valid values are accepted.
     *
     * @dataProvider attachmentsIgnoreProvider
     */
    public function testSetAttachmentsIgnore(bool $paramValue): void
    {
        $mailbox = $this->getMailbox();
        $mailbox->setAttachmentsIgnore($paramValue);
        $this->assertEquals($mailbox->getAttachmentsIgnore(), $paramValue);
    }

    /**
     * Provides test data for testing encoding.
     *
     * @psalm-return array<string, array{0:string}>
     */
    public function encodingTestStringsProvider(): array
    {
        return [
            'Avañe’ẽ' => ['Avañe’ẽ'], // Guaraní
            'azərbaycanca' => ['azərbaycanca'], // Azerbaijani (Latin)
            'Bokmål' => ['Bokmål'], // Norwegian Bokmål
            'chiCheŵa' => ['chiCheŵa'], // Chewa
            'Deutsch' => ['Deutsch'], // German
            'U.S. English' => ['U.S. English'], // U.S. English
            'français' => ['français'], // French
            'Éléments envoyés' => ['Éléments envoyés'], // issue 499
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
            'Азәрбајҹан' => ['Азәрбајҹан'], // Azerbaijani (Cyrillic)
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
    public function testEncodingToUtf7DecodeBackToUtf8(string $str): void
    {
        $mailbox = $this->getMailbox();

        $utf7_encoded_str = $mailbox->encodeStringToUtf7Imap($str);
        $utf8_decoded_str = $mailbox->decodeStringFromUtf7ImapToUtf8($utf7_encoded_str);

        $this->assertEquals($utf8_decoded_str, $str);
    }

    /**
     * Test, that strings encoded to UTF-7 can be decoded back to UTF-8.
     *
     * @dataProvider encodingTestStringsProvider
     */
    public function testMimeDecodingReturnsCorrectValues(string $str): void
    {
        $this->assertEquals($this->getMailbox()->decodeMimeStr($str), $str);
    }

    /**
     * Provides test data for testing parsing datetimes.
     *
     * @psalm-return array<string, array{0:string, 1:int}>
     */
    public function datetimeProvider(): array
    {
        return [
            'Sun, 14 Aug 2005 16:13:03 +0000 (CEST)' => ['2005-08-14T16:13:03+00:00', 1124035983],
            'Sun, 14 Aug 2005 16:13:03 +0000' => ['2005-08-14T16:13:03+00:00', 1124035983],

            'Sun, 14 Aug 2005 16:13:03 +1000 (CEST)' => ['2005-08-14T06:13:03+00:00', 1123999983],
            'Sun, 14 Aug 2005 16:13:03 +1000' => ['2005-08-14T06:13:03+00:00', 1123999983],
            'Sun, 14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', 1124071983],

            'Sun, 14 Aug 2005 16:13:03 +1100 (CEST)' => ['2005-08-14T05:13:03+00:00', 1123996383],
            'Sun, 14 Aug 2005 16:13:03 +1100' => ['2005-08-14T05:13:03+00:00', 1123996383],
            'Sun, 14 Aug 2005 16:13:03 -1100' => ['2005-08-15T03:13:03+00:00', 1124075583],

            '14 Aug 2005 16:13:03 +1000 (CEST)' => ['2005-08-14T06:13:03+00:00', 1123999983],
            '14 Aug 2005 16:13:03 +1000' => ['2005-08-14T06:13:03+00:00', 1123999983],
            '14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', 1124071983],
        ];
    }

    /**
     * Test, different datetimes conversions using differents timezones.
     *
     * @dataProvider datetimeProvider
     */
    public function testParsedDateDifferentTimeZones(string $dateToParse, int $epochToCompare): void
    {
        $parsedDt = $this->getMailbox()->parseDateTime($dateToParse);
        $parsedDateTime = new DateTime($parsedDt);
        $this->assertEquals((int) $parsedDateTime->format('U'), $epochToCompare);
    }

    /**
     * Provides test data for testing parsing invalid / unparseable datetimes.
     *
     * @psalm-return array<string, array{0:string}>
     */
    public function invalidDatetimeProvider(): array
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
    public function testParsedDateWithUnparseableDateTime(string $dateToParse): void
    {
        $parsedDt = $this->getMailbox()->parseDateTime($dateToParse);
        $this->assertEquals($parsedDt, $dateToParse);
    }

    /**
     * Test, parsed datetime being emtpy the header date.
     */
    public function testParsedDateTimeWithEmptyHeaderDate(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->getMailbox()->parseDateTime('');
    }

    /**
     * Provides test data for testing mime encoding.
     *
     * @return string[][]
     *
     * @psalm-return list<array{0:string, 1:string}>
     */
    public function mimeEncodingProvider(): array
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
    public function testMimeEncoding(string $str, string $expected): void
    {
        $mailbox = $this->getMailbox();

        if (empty($expected)) {
            $this->expectException(Exception::class);
            $mailbox->decodeMimeStr($str);
        } else {
            $this->assertEquals($mailbox->decodeMimeStr($str), $expected);
        }
    }

    /**
     * Provides test data for testing timeouts.
     *
     * @psalm-return array<string, array{0:'assertNull'|'expectException', 1:int, 2:list<1|2|3|4>}>
     */
    public function timeoutsProvider(): array
    {
        /** @psalm-var array<string, array{0:'assertNull'|'expectException', 1:int, 2:list<int>}> */
        return [
            'array(IMAP_OPENTIMEOUT)' => ['assertNull', 1, [IMAP_OPENTIMEOUT]],
            'array(IMAP_READTIMEOUT)' => ['assertNull', 1, [IMAP_READTIMEOUT]],
            'array(IMAP_WRITETIMEOUT)' => ['assertNull', 1, [IMAP_WRITETIMEOUT]],
            'array(IMAP_CLOSETIMEOUT)' => ['assertNull', 1, [IMAP_CLOSETIMEOUT]],
            'array(IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT)' => ['assertNull', 1, [IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT]],
        ];
    }

    /**
     * Test, that only supported timeouts can be set.
     *
     * @dataProvider timeoutsProvider
     *
     * @param int[] $types
     *
     * @psalm-param 'assertNull'|'expectException' $assertMethod
     * @psalm-param list<1|2|3|4> $types
     */
    public function testSetTimeouts(string $assertMethod, int $timeout, array $types): void
    {
        $mailbox = $this->getMailbox();

        if ('expectException' == $assertMethod) {
            $this->expectException(InvalidParameterException::class);
            $mailbox->setTimeouts($timeout, $types);
        } else {
            $this->assertNull($mailbox->setTimeouts($timeout, $types));
        }
    }

    /**
     * Provides test data for testing connection args.
     *
     * @psalm-return list<array{0:'assertNull'|'expectException', 1:int, 2:int, 3:array{DISABLE_AUTHENTICATOR?:string}|array<empty, empty>}>
     */
    public function connectionArgsProvider(): array
    {
        /** @psalm-var list<array{0:'assertNull'|'expectException', 1:int, 2:int, 3:array{DISABLE_AUTHENTICATOR?:string}|array<empty, empty>}> */
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

            ['expectException', OP_READONLY | OP_DEBUG, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, -1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, -3, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, -12, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, -1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            ['expectException', OP_READONLY, 0, [null]],
        ];
    }

    /**
     * Test, that only supported and valid connection args can be set.
     *
     * @dataProvider connectionArgsProvider
     *
     * @psalm-param array{DISABLE_AUTHENTICATOR?:string}|array<empty, empty> $param
     */
    public function testSetConnectionArgs(string $assertMethod, int $option, int $retriesNum, array $param = null): void
    {
        $mailbox = $this->getMailbox();

        if ('expectException' == $assertMethod) {
            $this->expectException(InvalidParameterException::class);
            $mailbox->setConnectionArgs($option, $retriesNum, $param);
        } elseif ('assertNull' == $assertMethod) {
            $this->assertNull($mailbox->setConnectionArgs($option, $retriesNum, $param));
        }
    }

    /**
     * Provides test data for testing mime string decoding.
     *
     * @psalm-return array<string, array{0:string, 1:string, 2?:string}>
     */
    public function mimeStrDecodingProvider(): array
    {
        return [
            '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>' => ['<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>', '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>'],
            '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>' => ['<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>', '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>'],
            '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>' => ['<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>', '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>'],
            '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>' => ['<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>', '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>'],
            'Some subject here 😘' => ['=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=', 'Some subject here 😘'],
            'mountainguan测试' => ['=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=', 'mountainguan测试'],
            "This is the Euro symbol ''." => ["This is the Euro symbol ''.", "This is the Euro symbol ''."],
            'Some subject here 😘 US-ASCII' => ['=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=', 'Some subject here 😘', 'US-ASCII'],
            'mountainguan测试 US-ASCII' => ['=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=', 'mountainguan测试', 'US-ASCII'],
            'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something' => ['مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something', 'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something'],
            '(事件单编号:TESTA-111111)(通报)入口有陌生人' => ['=?utf-8?b?KOS6i+S7tuWNlee8luWPtzpURVNUQS0xMTExMTEpKOmAmuaKpSnl?= =?utf-8?b?haXlj6PmnInpmYznlJ/kuro=?=', '(事件单编号:TESTA-111111)(通报)入口有陌生人'],
        ];
    }

    /**
     * Test, that decoding mime strings return unchanged / not broken strings.
     *
     * @dataProvider mimeStrDecodingProvider
     */
    public function testDecodeMimeStr(string $str, string $expectedStr, string $serverEncoding = 'utf-8'): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setServerEncoding($serverEncoding);
        $this->assertEquals($mailbox->decodeMimeStr($str), $expectedStr);
    }

    /**
     * Provides test data for testing base64 string decoding.
     *
     * @psalm-return list<array{0:string, 1:string}>
     */
    public function Base64DecodeProvider()
    {
        return [
            ['bm8tcmVwbHlAZXhhbXBsZS5jb20=', 'no-reply@example.com'],
            ['TWFuIGlzIGRpc3Rpbmd1aXNoZWQsIG5vdCBvbmx5IGJ5IGhpcyByZWFzb24sIGJ1dCBieSB0aGlzIHNpbmd1bGFyIHBhc3Npb24gZnJvbSBvdGhlciBhbmltYWxzLCB3aGljaCBpcyBhIGx1c3Qgb2YgdGhlIG1pbmQsIHRoYXQgYnkgYSBwZXJzZXZlcmFuY2Ugb2YgZGVsaWdodCBpbiB0aGUgY29udGludWVkIGFuZCBpbmRlZmF0aWdhYmxlIGdlbmVyYXRpb24gb2Yga25vd2xlZGdlLCBleGNlZWRzIHRoZSBzaG9ydCB2ZWhlbWVuY2Ugb2YgYW55IGNhcm5hbCBwbGVhc3VyZS4K', 'Man is distinguished, not only by his reason, but by this singular passion from other animals, which is a lust of the mind, that by a perseverance of delight in the continued and indefatigable generation of knowledge, exceeds the short vehemence of any carnal pleasure.'."\n"],
            ['SSBjYW4gZWF0IGdsYXNzIGFuZCBpdCBkb2VzIG5vdCBodXJ0IG1lLg==', 'I can eat glass and it does not hurt me.'],
            ['77u/4KSV4KS+4KSa4KSCIOCktuCkleCljeCkqOCli+CkruCljeCkr+CkpOCljeCkpOClgeCkruCljSDgpaQg4KSo4KWL4KSq4KS54KS/4KSo4KS44KWN4KSk4KS/IOCkruCkvuCkruCljSDgpaU=', '﻿काचं शक्नोम्यत्तुम् । नोपहिनस्ति माम् ॥'],
            ['SmUgcGV1eCBtYW5nZXIgZHUgdmVycmUsIMOnYSBuZSBtZSBmYWl0IHBhcyBtYWwu', 'Je peux manger du verre, ça ne me fait pas mal.'],
            ['UG90IHPEgyBtxINuw6JuYyBzdGljbMSDIMiZaSBlYSBudSBtxIMgcsSDbmXImXRlLg==', 'Pot să mănânc sticlă și ea nu mă rănește.'],
            ['5oiR6IO95ZCe5LiL546755KD6ICM5LiN5YK36Lqr6auU44CC', '我能吞下玻璃而不傷身體。'],
        ];
    }

    /**
     * @dataProvider Base64DecodeProvider
     */
    public function testBase64Decode(string $input, string $expected): void
    {
        $this->assertSame($expected, \imap_base64(\preg_replace('~[^a-zA-Z0-9+=/]+~s', '', $input)));
        $this->assertSame($expected, \base64_decode($input, false));
    }

    /**
     * @psalm-return list<array{0:string, 1:string, 2:class-string<\Exception>, 3:string}>
     */
    public function attachmentDirFailureProvider(): array
    {
        return [
            [
                __DIR__,
                '',
                InvalidParameterException::class,
                'setAttachmentsDir() expects a string as first parameter!',
            ],
            [
                __DIR__,
                ' ',
                InvalidParameterException::class,
                'setAttachmentsDir() expects a string as first parameter!',
            ],
            [
                __DIR__,
                __FILE__,
                InvalidParameterException::class,
                'Directory "'.__FILE__.'" not found',
            ],
        ];
    }

    /**
     * Test that setting the attachments directory fails when expected.
     *
     * @dataProvider attachmentDirFailureProvider
     *
     * @psalm-param class-string<\Exception> $expectedException
     */
    public function testAttachmentDirFailure(string $initialDir, string $attachmentsDir, string $expectedException, string $expectedExceptionMessage): void
    {
        $mailbox = new Mailbox('', '', '', $initialDir);

        $this->assertSame(\trim($initialDir), $mailbox->getAttachmentsDir());

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $mailbox->setAttachmentsDir($attachmentsDir);
    }

    protected function getMailbox(): Mailbox
    {
        return new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, $this->serverEncoding);
    }
}
