<?php

use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase
{
    /**
     * Provides list of extensions, which are required by this library.
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
     */
    public function testRequiredExtensionsAreEnabled($extension)
    {
        $this->assertTrue(extension_loaded($extension));
    }
}
