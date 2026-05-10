<?php

declare(strict_types=1);

namespace PhpImap;

use const FILEINFO_MIME_TYPE;

use PHPUnit\Framework\TestCase;

final class IncomingMailAttachmentTest extends TestCase
{
    public function testGetFileInfoReturnsEmptyStringWhenFinfoBufferFails(): void
    {
        $attachment = new class() extends IncomingMailAttachment {
            public function getContents(): string
            {
                return 'broken-image-contents';
            }

            protected function detectFileInfo(int $fileinfo_const, string $contents)
            {
                return false;
            }
        };

        $this->assertSame('', $attachment->getFileInfo(FILEINFO_MIME_TYPE));
    }

    public function testGetFileInfoReturnsDetectedStringWhenFinfoBufferSucceeds(): void
    {
        $attachment = new class() extends IncomingMailAttachment {
            public function getContents(): string
            {
                return 'png-contents';
            }

            protected function detectFileInfo(int $fileinfo_const, string $contents)
            {
                return 'image/png';
            }
        };

        $this->assertSame('image/png', $attachment->getFileInfo(FILEINFO_MIME_TYPE));
    }
}
