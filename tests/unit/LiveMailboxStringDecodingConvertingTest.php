<?php
/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author Sebi94nbg
 */
declare(strict_types=1);

namespace PhpImap;

use const ENCQUOTEDPRINTABLE;
use Generator;
use PHPUnit\Framework\TestCase;

class LiveMailboxStringDecodingConvertingTest extends TestCase
{
    /**
     * Provides data for testing string decoding.
     */
    public function stringDecodeProvider(): Generator
    {
        yield 'Issue #250 iso-8859-1' => [
            ENCQUOTEDPRINTABLE,
            'iso-8859-1',
            'mountainguan',
            'mountainguan',
            'e94a37111edb29a8d3f6078dc4810953964f19562613cf2bd15e21b69d30822a',
        ];

        yield 'Issue #250 utf-7' => [
            ENCQUOTEDPRINTABLE,
            'utf-7',
            '+bUuL1Q-',
            '测试',
            '6aa8f49cc992dfd75a114269ed26de0ad6d4e7d7a70d9c8afb3d7a57a88a73ed',
        ];

        yield 'Issue #250 utf-7 with chinese' => [
            ENCQUOTEDPRINTABLE,
            'utf-7',
            'mountainguan+bUuL1Q-',
            'mountainguan测试',
            '62a5022b682b7e02bda8d18424fa06501cdd71cce2832e95129673f63da2e177',
        ];

        yield 'Issue #250 utf-8 with chinese' => [
            ENCQUOTEDPRINTABLE,
            'utf-8',
            'mountainguan=E6=B5=8B=E8=AF=95',
            'mountainguan测试',
            '62a5022b682b7e02bda8d18424fa06501cdd71cce2832e95129673f63da2e177',
        ];

        yield 'Issue #657' => [
            ENCQUOTEDPRINTABLE,
            'iso-8859-2',
            '=EC=B9=E8=F8=BE=FD=E1=ED=E9',
            'ěščřžýáíé',
            'a05e42c7e14de716cd501e135f3f5e49545f71069de316a1e9f7bb153f9a7356',
        ];

        yield 'Emoji utf-8' => [
            ENCQUOTEDPRINTABLE,
            'utf-8',
            'Some subject here =F0=9F=98=98',
            'Some subject here 😘',
            'da66c62e7e82316b8b543f52f1ecc4415c4dc93bc87e2239ee5f98bdf00a8c50',
        ];
    }

    /**
     * Test that string decoding and converting works as expected.
     *
     * @dataProvider stringDecodeProvider
     */
    public function testStringDecode(int $encoding, string $charset, string $iso_8859_2, string $utf8, string $sha256): void
    {
        $mailbox = new Mailbox('', '', '');

        $dataInfo = new DataPartInfo($mailbox, 1337, '', $encoding, 0);
        $dataInfo->charset = $charset;

        $decoded = $dataInfo->decodeAfterFetch($iso_8859_2);

        $this->assertSame($utf8, $decoded);

        $this->assertSame($sha256, \hash('sha256', $decoded));
    }
}
