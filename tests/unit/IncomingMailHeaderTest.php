<?php

declare(strict_types=1);

namespace PhpImap;

use PHPUnit\Framework\TestCase;

final class IncomingMailHeaderTest extends TestCase
{
    public function testSetHeadersRawParsesFoldedRepeatedAndCaseInsensitiveHeaders(): void
    {
        $header = new IncomingMailHeader();
        $header->setHeadersRaw(
            "Origin-MessageID: <origin@example.com>\r\n".
            "X-Trace: first\r\n".
            "\tcontinued\r\n".
            "x-trace: second\r\n".
            "Subject: Example\r\n"
        );

        $this->assertSame('<origin@example.com>', $header->getHeader('origin-messageid'));
        $this->assertSame(
            ['first continued', 'second'],
            $header->getHeaders('X-TRACE')
        );
        $this->assertSame(
            [
                'origin-messageid' => ['<origin@example.com>'],
                'x-trace' => ['first continued', 'second'],
                'subject' => ['Example'],
            ],
            $header->getAllHeaders()
        );
    }

    public function testGetHeaderLazilyParsesDirectlyAssignedHeadersRaw(): void
    {
        $header = new IncomingMailHeader();
        $header->headersRaw = "X-Custom-Header: custom value\r\n";

        $this->assertSame('custom value', $header->getHeader('x-custom-header'));
        $this->assertSame(
            ['custom value'],
            $header->headersByName['x-custom-header']
        );
        $this->assertNull($header->getHeader('missing-header'));
        $this->assertSame([], $header->getHeaders('missing-header'));
    }

    public function testIncomingMailRetainsParsedHeadersAfterSetHeader(): void
    {
        $header = new IncomingMailHeader();
        $header->setHeadersRaw(
            "X-Custom-Header: custom value\r\n".
            "Received: mx1.example.test\r\n".
            "Received: mx2.example.test\r\n"
        );

        $mail = new IncomingMail();
        $mail->setHeader($header);

        $this->assertSame('custom value', $mail->getHeader('X-Custom-Header'));
        $this->assertSame(
            ['mx1.example.test', 'mx2.example.test'],
            $mail->getHeaders('received')
        );
    }
}
