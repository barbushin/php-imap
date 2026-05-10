<?php
/**
 * Mailbox header parsing focused unit tests.
 */
declare(strict_types=1);

namespace PhpImap;

use PHPUnit\Framework\TestCase;
use stdClass;

final class MailboxHeaderParsingTest extends TestCase
{
    public function testGetMailHeaderFieldValueUnfoldsFoldedHeaderLines(): void
    {
        $headersRaw =
            "References: <first@example.com>\r\n".
            "\t<second@example.com>\r\n".
            'Subject: Example'."\r\n";

        $mailbox = new Fixtures\Mailbox('', '', '');

        $this->assertSame(
            '<first@example.com> <second@example.com>',
            $mailbox->getMailHeaderFieldValueForTests($headersRaw, 'References')
        );
    }

    public function testGetThreadingHeadersFallsBackToRawHeadersWhenParsedMessageIdIsMissing(): void
    {
        $headersRaw =
            "Message-ID: <ticket-123@example.com>\r\n".
            "In-Reply-To: <parent-456@example.com>\r\n".
            "References: <root@example.com>\r\n".
            "\t<parent-456@example.com>\r\n";

        $head = new stdClass();

        $mailbox = new Fixtures\Mailbox('', '', '');

        $this->assertSame(
            [
                'messageId' => '<ticket-123@example.com>',
                'inReplyTo' => '<parent-456@example.com>',
                'references' => '<root@example.com> <parent-456@example.com>',
            ],
            $mailbox->getThreadingHeadersForTests($head, $headersRaw)
        );
    }

    public function testGetThreadingHeadersPrefersParsedMessageIdWhenAvailable(): void
    {
        $headersRaw = "Message-ID: <raw@example.com>\r\n";

        $head = new stdClass();
        $head->message_id = '<parsed@example.com>';

        $mailbox = new Fixtures\Mailbox('', '', '');

        $this->assertSame(
            [
                'messageId' => '<parsed@example.com>',
                'inReplyTo' => null,
                'references' => null,
            ],
            $mailbox->getThreadingHeadersForTests($head, $headersRaw)
        );
    }
}
