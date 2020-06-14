<?php
/**
* @author BAPCLTD-Marv
*/
declare(strict_types=1);

namespace PhpImap;

use const DATE_RFC3339;
use const ENCOTHER;
use PHPUnit\Framework\TestCase;

class IncomingMailTest extends TestCase
{
    public function testSetHeader(): void
    {
        $mail = new IncomingMail();
        $header = new IncomingMailHeader();

        $mail->id = 1;
        $header->id = 2;

        $mail->isDraft = true;
        $header->isDraft = false;

        $mail->date = \date(DATE_RFC3339, 0);
        $header->date = \date(DATE_RFC3339, 60 * 60 * 24);

        $mail->setHeader($header);

        foreach (
            [
                'id',
                'isDraft',
                'date',
            ] as $property
        ) {
            /** @var scalar|array|object|resource|null */
            $headerPropertyValue = $header->$property;
            $this->assertSame($headerPropertyValue, $mail->$property);
        }
    }

    public function testDataPartInfo(): void
    {
        $mail = new IncomingMail();
        $mailbox = new Mailbox('', '', '');

        $data_part = new Fixtures\DataPartInfo($mailbox, 1, 0, ENCOTHER, 0);
        $data_part->setData('foo');

        $this->assertSame('foo', $data_part->fetch());

        $mail->addDataPartInfo($data_part, DataPartInfo::TEXT_PLAIN);

        $this->assertSame('foo', $mail->textPlain);

        $this->assertTrue($mail->__isset('textPlain'));
    }

    public function testAttachments(): void
    {
        $mail = new IncomingMail();

        $this->assertFalse($mail->hasAttachments());
        $this->assertSame([], $mail->getAttachments());

        $attachments = [
            new IncomingMailAttachment(),
        ];

        foreach ($attachments as $i => $attachment) {
            $attachment->id = (string) $i;
            $mail->addAttachment($attachment);
        }

        $this->assertTrue($mail->hasAttachments());
        $this->assertSame($attachments, $mail->getAttachments());

        foreach ($attachments as $attachment) {
            $this->assertIsString($attachment->id);
            $this->assertTrue($mail->removeAttachment($attachment->id));
        }

        $this->assertFalse($mail->hasAttachments());
        $this->assertSame([], $mail->getAttachments());

        foreach ($attachments as $attachment) {
            $this->assertIsString($attachment->id);
            $this->assertFalse($mail->removeAttachment($attachment->id));
        }
    }
}
