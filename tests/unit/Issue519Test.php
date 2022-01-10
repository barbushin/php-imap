<?php
/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author BAPCLTD-Marv
 */
declare(strict_types=1);

namespace PhpImap;

use const ENC8BIT;
use const ENCBASE64;
use PHPUnit\Framework\TestCase;
use const TYPEIMAGE;

class Issue519Test extends TestCase
{
    public const HEADER_VALUES = [
        'inline',
        'Inline',
        'iNline',
        'INline',
        'inLine',
        'InLine',
        'iNLine',
        'INLine',
        'inlIne',
        'InlIne',
        'iNlIne',
        'INlIne',
        'inLIne',
        'InLIne',
        'iNLIne',
        'INLIne',
        'inliNe',
        'InliNe',
        'iNliNe',
        'INliNe',
        'inLiNe',
        'InLiNe',
        'iNLiNe',
        'INLiNe',
        'inlINe',
        'InlINe',
        'iNlINe',
        'INlINe',
        'inLINe',
        'InLINe',
        'iNLINe',
        'INLINe',
        'inlinE',
        'InlinE',
        'iNlinE',
        'INlinE',
        'inLinE',
        'InLinE',
        'iNLinE',
        'INLinE',
        'inlInE',
        'InlInE',
        'iNlInE',
        'INlInE',
        'inLInE',
        'InLInE',
        'iNLInE',
        'INLInE',
        'inliNE',
        'InliNE',
        'iNliNE',
        'INliNE',
        'inLiNE',
        'InLiNE',
        'iNLiNE',
        'INLiNE',
        'inlINE',
        'InlINE',
        'iNlINE',
        'INlINE',
        'inLINE',
        'InLINE',
        'iNLINE',
        'INLINE',
    ];

    public const CID = 'cid:foo.jpg';

    public const ID = 'foo.jpg';

    public const SUBTYPE = 'jpeg';

    public const SIZE_IN_BYTES = 0;

    public const HTML = 'foo.html';

    public const HTML_EMBED = '<img src="data:image/jpeg;base64, ">';

    public const MIME_TYPE = 'image/jpeg';

    public const EXPECTED_ATTACHMENT_COUNT = 1;

    public const EXPECTED_ATTACHMENT_COUNT_AFTER_EMBED = 0;

    /**
     * @psalm-return array<string, array{0: string}>
     *
     * @return string[][]
     */
    public function provider(): array
    {
        $out = [];

        foreach (self::HEADER_VALUES as $value) {
            $out[$value] = [$value];
        }

        return $out;
    }

    /**
     * @dataProvider provider
     */
    public function test(string $header_value): void
    {
        $mailbox = new Mailbox('', '', '');
        $mail = new IncomingMail();
        $attachment = new Fixtures\IncomingMailAttachment();
        $part = new Fixtures\DataPartInfo(
            $mailbox,
            0,
            0,
            ENCBASE64,
            0
        );

        $html = new Fixtures\DataPartInfo(
            $mailbox,
            0,
            0,
            ENC8BIT,
            0
        );

        $html_string = '<img src="'.self::CID.'">';

        $html->setData($html_string);
        $part->setData('');

        $attachment->id = self::ID;
        $attachment->contentId = self::ID;
        $attachment->type = TYPEIMAGE;
        $attachment->encoding = ENCBASE64;
        $attachment->subtype = self::SUBTYPE;
        $attachment->description = self::ID;
        $attachment->name = self::ID;
        $attachment->sizeInBytes = self::SIZE_IN_BYTES;
        $attachment->disposition = $header_value;
        $attachment->override_getFileInfo_mime_type = self::MIME_TYPE;

        $attachment->addDataPartInfo($part);

        $mail->addDataPartInfo($html, DataPartInfo::TEXT_HTML);
        $mail->addAttachment($attachment);

        $this->assertTrue($mail->hasAttachments());

        $this->assertCount(
            self::EXPECTED_ATTACHMENT_COUNT,
            $mail->getAttachments()
        );

        $this->assertSame($html_string, $mail->textHtml);

        $mail->embedImageAttachments();

        $this->assertCount(
            self::EXPECTED_ATTACHMENT_COUNT_AFTER_EMBED,
            $mail->getAttachments()
        );

        $this->assertSame(self::HTML_EMBED, $mail->textHtml);
    }
}
