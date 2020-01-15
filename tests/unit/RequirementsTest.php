<?php

declare(strict_types=1);

namespace PhpImap;

use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase
{
    /**
     * Provides list of extensions, which are required by this library.
     *
     * @psalm-return array<string, array{0:string}>
     */
    public function extensionProvider(): array
    {
        return [
            'imap' => ['imap'],
            'mbstring' => ['mbstring'],
            'iconv' => ['iconv'],
        ];
    }

    /**
     * Test, that required modules are enabled.
     *
     * @dataProvider extensionProvider
     *
     * @return void
     */
    public function testRequiredExtensionsAreEnabled(string $extension)
    {
        $this->assertTrue(\extension_loaded($extension));
    }
}
