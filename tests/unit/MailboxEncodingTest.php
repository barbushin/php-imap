<?php

/**
 * Mailbox encoding focused unit tests.
 */
declare(strict_types=1);

namespace PhpImap;

use PHPUnit\Framework\TestCase;

final class MailboxEncodingTest extends TestCase
{
    /**
     * @return array<string, array{0:string, 1:string, 2:string, 3?:string}>
     */
    public function convertToUtf8Provider(): array
    {
        return [
            'default charset uses configured alias' => [
                "Price \x8010",
                'default',
                'Price €10',
                'cp1252',
            ],
            'iconv alias fallback' => [
                "Price \x8010",
                'cp1252',
                'Price €10',
            ],
            'unknown charset returns original bytes' => [
                "caf\xe9",
                'x-unknown-charset',
                "caf\xe9",
            ],
        ];
    }

    /**
     * @dataProvider convertToUtf8Provider
     */
    public function testConvertToUtf8(string $input, string $charset, string $expected, string $defaultCharset = 'default'): void
    {
        $mailbox = new Fixtures\Mailbox('', '', '');
        $mailbox->decodeMimeStrDefaultCharset = $defaultCharset;

        $this->assertSame($expected, $mailbox->convertToUtf8($input, $charset));
    }

    /**
     * @return array<string, array{0:string, 1:bool}>
     */
    public function isUrlEncodedProvider(): array
    {
        return [
            'RFC2231 encoded segment' => ['%E2%82%AC%20rates.txt', true],
            'lowercase hex escapes' => ['%e2%82%ac%20rates.txt', true],
            'encoded mime-word' => ['%3D%3FUTF-8%3FQ%3Fmountainguan%3DE6%3DB5%3D8B%3DE8%3DAF%3D95%3F%3D', true],
            'plain ascii' => ['plain-file.txt', false],
            'invalid percent escape' => ['%ZZrates.txt', false],
            'mixed valid and invalid percent escapes' => ['%E2%82%ZZrates.txt', false],
            'lone percent sign' => ['rates%.txt', false],
        ];
    }

    /**
     * @dataProvider isUrlEncodedProvider
     */
    public function testIsUrlEncoded(string $input, bool $expected): void
    {
        $this->assertSame($expected, (new Fixtures\Mailbox('', '', ''))->isUrlEncoded($input));
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public function decodeRFC2231Provider(): array
    {
        return [
            'plain ascii filename strips RFC2231 metadata' => [
                "utf-8''plain-file.txt",
                'plain-file.txt',
            ],
            'utf-8 filename with language tag' => [
                "utf-8'de'%E2%82%AC%20rates.txt",
                '€ rates.txt',
            ],
            'url-encoded mime encoded-word' => [
                "utf-8'en'%3D%3FUTF-8%3FQ%3Fmountainguan%3DE6%3DB5%3D8B%3DE8%3DAF%3D95%3F%3D",
                'mountainguan测试',
            ],
            'non RFC2231 string is unchanged' => [
                'plain-file.txt',
                'plain-file.txt',
            ],
        ];
    }

    /**
     * @dataProvider decodeRFC2231Provider
     */
    public function testDecodeRFC2231(string $input, string $expected): void
    {
        $mailbox = new Fixtures\Mailbox('', '', '');

        $this->assertSame($expected, $mailbox->decodeRFC2231ForTests($input));
    }

    public function testDecodeStringFromUtf7ImapToUtf8DecodesMailboxNamesToUtf8(): void
    {
        $mailbox = new Fixtures\Mailbox('', '', '');
        $encodedMailboxName = '{imap.example.com:993/imap/ssl}INBOX.&bUuL1Q-';

        $this->assertSame(
            '{imap.example.com:993/imap/ssl}INBOX.测试',
            $mailbox->decodeStringFromUtf7ImapToUtf8($encodedMailboxName)
        );
    }
}
