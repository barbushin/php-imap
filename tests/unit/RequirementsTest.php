<?php

use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase
{
    /**
     * Provides list of extensions, which are required by this library.
     *
     * @psalm-return array<string, array{0:string}>
     */
    public function extensionProvider()
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
     * @param string $extension
     *
     * @return void
     */
    public function testRequiredExtensionsAreEnabled($extension)
    {
        $this->assertTrue(extension_loaded($extension));
    }
}
