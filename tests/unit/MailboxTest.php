<?php
/**
 * Mailbox - PHPUnit tests.
 *
 * @author Sebastian Kraetzig <sebastian-kraetzig@gmx.de>
 */
declare(strict_types=1);

namespace PhpImap;

use const CL_EXPUNGE;
use DateTime;
use Generator;
use const IMAP_CLOSETIMEOUT;
use const IMAP_OPENTIMEOUT;
use const IMAP_READTIMEOUT;
use const IMAP_WRITETIMEOUT;
use const OP_ANONYMOUS;
use const OP_DEBUG;
use const OP_HALFOPEN;
use const OP_PROTOTYPE;
use const OP_READONLY;
use const OP_SECURE;
use const OP_SHORTCACHE;
use const OP_SILENT;
use PhpImap\Exceptions\InvalidParameterException;
use PHPUnit\Framework\TestCase;
use const SE_FREE;
use const SE_UID;

final class MailboxTest extends TestCase
{
    public const ANYTHING = 0;

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
     * @psalm-return non-empty-list<array{0: 'UTF-8'|'Windows-1251'|'Windows-1252'}>
     *
     * @return string[][]
     */
    public function SetAndGetServerEncodingProvider(): array
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
     * @return (bool|string)[][]
     *
     * @psalm-return array{UTF-7: array{0: true, 1: 'UTF-7'}, UTF7-IMAP: array{0: true, 1: 'UTF7-IMAP'}, UTF-8: array{0: true, 1: 'UTF-8'}, ASCII: array{0: true, 1: 'ASCII'}, US-ASCII: array{0: true, 1: 'US-ASCII'}, ISO-8859-1: array{0: true, 1: 'ISO-8859-1'}, UTF7: array{0: false, 1: 'UTF7'}, UTF-7-IMAP: array{0: false, 1: 'UTF-7-IMAP'}, UTF-7IMAP: array{0: false, 1: 'UTF-7IMAP'}, UTF8: array{0: false, 1: 'UTF8'}, USASCII: array{0: false, 1: 'USASCII'}, ASC11: array{0: false, 1: 'ASC11'}, ISO-8859-0: array{0: false, 1: 'ISO-8859-0'}, ISO-8855-1: array{0: false, 1: 'ISO-8855-1'}, ISO-8859: array{0: false, 1: 'ISO-8859'}}
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
     * @psalm-return array{0: array{0: '0'}, 1: array{0: '1'}, 2: array{0: '2'}, 3: array{0: '3'}, 4: array{0: '4'}, 5: array{0: '5'}, 6: array{0: '6'}, 7: array{0: '7'}, 8: array{0: '8'}, 9: array{0: '9'}, a: array{0: 'a'}, b: array{0: 'b'}, c: array{0: 'c'}, d: array{0: 'd'}, e: array{0: 'e'}, f: array{0: 'f'}, g: array{0: 'g'}, h: array{0: 'h'}, i: array{0: 'i'}, j: array{0: 'j'}, k: array{0: 'k'}, l: array{0: 'l'}, m: array{0: 'm'}, n: array{0: 'n'}, o: array{0: 'o'}, p: array{0: 'p'}, q: array{0: 'q'}, r: array{0: 'r'}, s: array{0: 's'}, t: array{0: 't'}, u: array{0: 'u'}, v: array{0: 'v'}, w: array{0: 'w'}, x: array{0: 'x'}, y: array{0: 'y'}, z: array{0: 'z'}, !: array{0: '!'}, '\\': array{0: '\'}, $: array{0: '$'}, %: array{0: '%'}, §: array{0: '§'}, &: array{0: '&'}, /: array{0: '/'}, (: array{0: '('}, ): array{0: ')'}, =: array{0: '='}, #: array{0: '#'}, ~: array{0: '~'}, *: array{0: '*'}, +: array{0: '+'}, ,: array{0: ','}, ;: array{0: ';'}, '.': array{0: '.'}, ':': array{0: ':'}, <: array{0: '<'}, >: array{0: '>'}, |: array{0: '|'}, _: array{0: '_'}}
     *
     * @return string[][]
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
     * @psalm-return array{Avañe’ẽ: array{0: 'Avañe’ẽ'}, azərbaycanca: array{0: 'azərbaycanca'}, Bokmål: array{0: 'Bokmål'}, chiCheŵa: array{0: 'chiCheŵa'}, Deutsch: array{0: 'Deutsch'}, 'U.S. English': array{0: 'U.S. English'}, français: array{0: 'français'}, 'Éléments envoyés': array{0: 'Éléments envoyés'}, føroyskt: array{0: 'føroyskt'}, Kĩmĩrũ: array{0: 'Kĩmĩrũ'}, Kɨlaangi: array{0: 'Kɨlaangi'}, oʼzbekcha: array{0: 'oʼzbekcha'}, Plattdüütsch: array{0: 'Plattdüütsch'}, română: array{0: 'română'}, Sängö: array{0: 'Sängö'}, 'Tiếng Việt': array{0: 'Tiếng Việt'}, ɔl-Maa: array{0: 'ɔl-Maa'}, Ελληνικά: array{0: 'Ελληνικά'}, Ўзбек: array{0: 'Ўзбек'}, Азәрбајҹан: array{0: 'Азәрбајҹан'}, Српски: array{0: 'Српски'}, русский: array{0: 'русский'}, 'ѩзыкъ словѣньскъ': array{0: 'ѩзыкъ словѣньскъ'}, العربية: array{0: 'العربية'}, नेपाली: array{0: 'नेपाली'}, 日本語: array{0: '日本語'}, 简体中文: array{0: '简体中文'}, 繁體中文: array{0: '繁體中文'}, 한국어: array{0: '한국어'}, ąčęėįšųūžĄČĘĖĮŠŲŪŽ: array{0: 'ąčęėįšųūžĄČĘĖĮŠŲŪŽ'}}
     *
     * @return string[][]
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
     * @psalm-return array{'Sun, 14 Aug 2005 16:13:03 +0000 (CEST)': array{0: '2005-08-14T16:13:03+00:00', 1: 1124035983}, 'Sun, 14 Aug 2005 16:13:03 +0000': array{0: '2005-08-14T16:13:03+00:00', 1: 1124035983}, 'Sun, 14 Aug 2005 16:13:03 +1000 (CEST)': array{0: '2005-08-14T06:13:03+00:00', 1: 1123999983}, 'Sun, 14 Aug 2005 16:13:03 +1000': array{0: '2005-08-14T06:13:03+00:00', 1: 1123999983}, 'Sun, 14 Aug 2005 16:13:03 -1000': array{0: '2005-08-15T02:13:03+00:00', 1: 1124071983}, 'Sun, 14 Aug 2005 16:13:03 +1100 (CEST)': array{0: '2005-08-14T05:13:03+00:00', 1: 1123996383}, 'Sun, 14 Aug 2005 16:13:03 +1100': array{0: '2005-08-14T05:13:03+00:00', 1: 1123996383}, 'Sun, 14 Aug 2005 16:13:03 -1100': array{0: '2005-08-15T03:13:03+00:00', 1: 1124075583}, '14 Aug 2005 16:13:03 +1000 (CEST)': array{0: '2005-08-14T06:13:03+00:00', 1: 1123999983}, '14 Aug 2005 16:13:03 +1000': array{0: '2005-08-14T06:13:03+00:00', 1: 1123999983}, '14 Aug 2005 16:13:03 -1000': array{0: '2005-08-15T02:13:03+00:00', 1: 1124071983}}
     *
     * @return (int|string)[][]
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
     * @psalm-return array{'Sun, 14 Aug 2005 16:13:03 +9000 (CEST)': array{0: 'Sun, 14 Aug 2005 16:13:03 +9000 (CEST)'}, 'Sun, 14 Aug 2005 16:13:03 +9000': array{0: 'Sun, 14 Aug 2005 16:13:03 +9000'}, 'Sun, 14 Aug 2005 16:13:03 -9000': array{0: 'Sun, 14 Aug 2005 16:13:03 -9000'}}
     *
     * @return string[][]
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
     * @psalm-return array{0: array{0: '=?iso-8859-1?Q?Sebastian_Kr=E4tzig?= <sebastian.kraetzig@example.com>', 1: 'Sebastian Krätzig <sebastian.kraetzig@example.com>'}, 1: array{0: '=?iso-8859-1?Q?Sebastian_Kr=E4tzig?=', 1: 'Sebastian Krätzig'}, 2: array{0: 'sebastian.kraetzig', 1: 'sebastian.kraetzig'}, 3: array{0: '=?US-ASCII?Q?Keith_Moore?= <km@ab.example.edu>', 1: 'Keith Moore <km@ab.example.edu>'}, 4: array{0: '   ', 1: '   '}, 5: array{0: '=?ISO-8859-1?Q?Max_J=F8rn_Simsen?= <max.joern.s@example.dk>', 1: 'Max Jørn Simsen <max.joern.s@example.dk>'}, 6: array{0: '=?ISO-8859-1?Q?Andr=E9?= Muster <andre.muster@vm1.ulg.ac.be>', 1: 'André Muster <andre.muster@vm1.ulg.ac.be>'}, 7: array{0: '=?ISO-8859-1?B?SWYgeW91IGNhbiByZWFkIHRoaXMgeW8=?= =?ISO-8859-2?B?dSB1bmRlcnN0YW5kIHRoZSBleGFtcGxlLg==?=', 1: 'If you can read this you understand the example.'}, 8: array{0: '', 1: ''}}
     */
    public function mimeEncodingProvider(): array
    {
        return [
            ['=?iso-8859-1?Q?Sebastian_Kr=E4tzig?= <sebastian.kraetzig@example.com>', 'Sebastian Krätzig <sebastian.kraetzig@example.com>'],
            ['=?iso-8859-1?Q?Sebastian_Kr=E4tzig?=', 'Sebastian Krätzig'],
            ['sebastian.kraetzig', 'sebastian.kraetzig'],
            ['=?US-ASCII?Q?Keith_Moore?= <km@ab.example.edu>', 'Keith Moore <km@ab.example.edu>'],
            ['   ', '   '],
            ['=?ISO-8859-1?Q?Max_J=F8rn_Simsen?= <max.joern.s@example.dk>', 'Max Jørn Simsen <max.joern.s@example.dk>'],
            ['=?ISO-8859-1?Q?Andr=E9?= Muster <andre.muster@vm1.ulg.ac.be>', 'André Muster <andre.muster@vm1.ulg.ac.be>'],
            ['=?ISO-8859-1?B?SWYgeW91IGNhbiByZWFkIHRoaXMgeW8=?= =?ISO-8859-2?B?dSB1bmRlcnN0YW5kIHRoZSBleGFtcGxlLg==?=', 'If you can read this you understand the example.'],
            ['', ''], // barbushin/php-imap#501
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

        $this->assertEquals($mailbox->decodeMimeStr($str), $expected);
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
     * @psalm-return Generator<string, array{0: 'assertNull'|'expectException', 1: int, 2: 0, 3: array<empty, empty>}, mixed, void>
     */
    public function connectionArgsProvider(): Generator
    {
        yield from [
            'readonly, disable gssapi' => ['assertNull', OP_READONLY, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'anonymous, disable gssapi' => ['assertNull', OP_ANONYMOUS, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'half open, disable gssapi' => ['assertNull', OP_HALFOPEN, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'expunge on close, disable gssapi' => ['assertNull', CL_EXPUNGE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'debug, disable gssapi' => ['assertNull', OP_DEBUG, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'short cache, disable gssapi' => ['assertNull', OP_SHORTCACHE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'silent, disable gssapi' => ['assertNull', OP_SILENT, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'return driver prototype, disable gssapi' => ['assertNull', OP_PROTOTYPE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'don\'t do non-secure authentication, disable gssapi' => ['assertNull', OP_SECURE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, disable gssapi, 1 retry' => ['assertNull', OP_READONLY, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, disable gssapi, 3 retries' => ['assertNull', OP_READONLY, 3, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, disable gssapi, 12 retries' => ['assertNull', OP_READONLY, 12, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly debug, disable gssapi' => ['assertNull', OP_READONLY | OP_DEBUG, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, -1 retries' => ['expectException', OP_READONLY, -1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, -3 retries' => ['expectException', OP_READONLY, -3, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, -12 retries' => ['expectException', OP_READONLY, -12, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, null options' => ['expectException', OP_READONLY, 0, [null]],
        ];

        /** @psalm-var list<array{0:int, 1:string}> */
        $options = [
            [OP_DEBUG, 'debug'], // 1
            [OP_READONLY, 'readonly'], // 2
            [OP_ANONYMOUS, 'anonymous'], // 4
            [OP_SHORTCACHE, 'short cache'], // 8
            [OP_SILENT, 'silent'], // 16
            [OP_PROTOTYPE, 'return driver prototype'], // 32
            [OP_HALFOPEN, 'half-open'], // 64
            [OP_SECURE, 'don\'t do non-secure authnetication'], // 256
            [CL_EXPUNGE, 'expunge on close'], // 32768
        ];

        foreach ($options as $i => $option) {
            $value = $option[0];

            for ($j = $i + 1; $j < \count($options); ++$j) {
                $value |= $options[$j][0];

                $fields = [];

                foreach ($options as $option) {
                    if (0 !== ($value & $option[0])) {
                        $fields[] = $option[1];
                    }
                }

                $key = \implode(', ', $fields);

                yield $key => ['assertNull', $value, 0, []];
                yield ('INVALID + '.$key) => ['expectException', $value | 128, 0, []];
            }
        }
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
            $this->assertSame($option, $mailbox->getImapOptions());
        } elseif ('assertNull' == $assertMethod) {
            $this->assertNull($mailbox->setConnectionArgs($option, $retriesNum, $param));
        }

        $mailbox->disconnect();
    }

    /**
     * Provides test data for testing mime string decoding.
     *
     * @psalm-return array{'<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>': array{0: '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>', 1: '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>'}, '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>': array{0: '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>', 1: '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>'}, '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>': array{0: '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>', 1: '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>'}, '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>': array{0: '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>', 1: '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>'}, 'Some subject here 😘': array{0: '=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=', 1: 'Some subject here 😘'}, mountainguan测试: array{0: '=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=', 1: 'mountainguan测试'}, 'This is the Euro symbol \'\'.': array{0: 'This is the Euro symbol ''.', 1: 'This is the Euro symbol ''.'}, 'Some subject here 😘 US-ASCII': array{0: '=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=', 1: 'Some subject here 😘', 2: 'US-ASCII'}, 'mountainguan测试 US-ASCII': array{0: '=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=', 1: 'mountainguan测试', 2: 'US-ASCII'}, 'مقتطفات من: صن تزو. \"فن الحرب\". كتب أبل. Something': array{0: 'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something', 1: 'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something'}, '(事件单编号:TESTA-111111)(通报)入口有陌生人': array{0: '=?utf-8?b?KOS6i+S7tuWNlee8luWPtzpURVNUQS0xMTExMTEpKOmAmuaKpSnl?= =?utf-8?b?haXlj6PmnInpmYznlJ/kuro=?=', 1: '(事件单编号:TESTA-111111)(通报)入口有陌生人'}}
     *
     * @return string[][]
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
     * @psalm-return array{0: array{0: 'bm8tcmVwbHlAZXhhbXBsZS5jb20=', 1: 'no-reply@example.com'}, 1: array{0: 'TWFuIGlzIGRpc3Rpbmd1aXNoZWQsIG5vdCBvbmx5IGJ5IGhpcyByZWFzb24sIGJ1dCBieSB0aGlzIHNpbmd1bGFyIHBhc3Npb24gZnJvbSBvdGhlciBhbmltYWxzLCB3aGljaCBpcyBhIGx1c3Qgb2YgdGhlIG1pbmQsIHRoYXQgYnkgYSBwZXJzZXZlcmFuY2Ugb2YgZGVsaWdodCBpbiB0aGUgY29udGludWVkIGFuZCBpbmRlZmF0aWdhYmxlIGdlbmVyYXRpb24gb2Yga25vd2xlZGdlLCBleGNlZWRzIHRoZSBzaG9ydCB2ZWhlbWVuY2Ugb2YgYW55IGNhcm5hbCBwbGVhc3VyZS4=', 1: 'Man is distinguished, not only by his reason, but by this singular passion from other animals, which is a lust of the mind, that by a perseverance of delight in the continued and indefatigable generation of knowledge, exceeds the short vehemence of any carnal pleasure.'}, 2: array{0: 'SSBjYW4gZWF0IGdsYXNzIGFuZCBpdCBkb2VzIG5vdCBodXJ0IG1lLg==', 1: 'I can eat glass and it does not hurt me.'}, 3: array{0: '77u/4KSV4KS+4KSa4KSCIOCktuCkleCljeCkqOCli+CkruCljeCkr+CkpOCljeCkpOClgeCkruCljSDgpaQg4KSo4KWL4KSq4KS54KS/4KSo4KS44KWN4KSk4KS/IOCkruCkvuCkruCljSDgpaU=', 1: '﻿काचं शक्नोम्यत्तुम् । नोपहिनस्ति माम् ॥'}, 4: array{0: 'SmUgcGV1eCBtYW5nZXIgZHUgdmVycmUsIMOnYSBuZSBtZSBmYWl0IHBhcyBtYWwu', 1: 'Je peux manger du verre, ça ne me fait pas mal.'}, 5: array{0: 'UG90IHPEgyBtxINuw6JuYyBzdGljbMSDIMiZaSBlYSBudSBtxIMgcsSDbmXImXRlLg==', 1: 'Pot să mănânc sticlă și ea nu mă rănește.'}, 6: array{0: '5oiR6IO95ZCe5LiL546755KD6ICM5LiN5YK36Lqr6auU44CC', 1: '我能吞下玻璃而不傷身體。'}}
     *
     * @return string[][]
     */
    public function Base64DecodeProvider(): array
    {
        return [
            ['bm8tcmVwbHlAZXhhbXBsZS5jb20=', 'no-reply@example.com'],
            ['TWFuIGlzIGRpc3Rpbmd1aXNoZWQsIG5vdCBvbmx5IGJ5IGhpcyByZWFzb24sIGJ1dCBieSB0aGlzIHNpbmd1bGFyIHBhc3Npb24gZnJvbSBvdGhlciBhbmltYWxzLCB3aGljaCBpcyBhIGx1c3Qgb2YgdGhlIG1pbmQsIHRoYXQgYnkgYSBwZXJzZXZlcmFuY2Ugb2YgZGVsaWdodCBpbiB0aGUgY29udGludWVkIGFuZCBpbmRlZmF0aWdhYmxlIGdlbmVyYXRpb24gb2Yga25vd2xlZGdlLCBleGNlZWRzIHRoZSBzaG9ydCB2ZWhlbWVuY2Ugb2YgYW55IGNhcm5hbCBwbGVhc3VyZS4=', 'Man is distinguished, not only by his reason, but by this singular passion from other animals, which is a lust of the mind, that by a perseverance of delight in the continued and indefatigable generation of knowledge, exceeds the short vehemence of any carnal pleasure.'],
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
     * @psalm-return array{0: array{0: string, 1: '', 2: Exceptions\InvalidParameterException::class, 3: 'setAttachmentsDir() expects a string as first parameter!'}, 1: array{0: string, 1: ' ', 2: Exceptions\InvalidParameterException::class, 3: 'setAttachmentsDir() expects a string as first parameter!'}, 2: array{0: string, 1: string, 2: Exceptions\InvalidParameterException::class, 3: string}}
     *
     * @return string[][]
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

    protected function getMailbox(): Fixtures\Mailbox
    {
        return new Fixtures\Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir, $this->serverEncoding);
    }
}
