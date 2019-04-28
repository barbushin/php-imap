<?php

use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase
{
	function testPhpImapExtensionIsEnabled() {
		$this->assertTrue(extension_loaded('imap'));
	}
}
