<?php

declare(strict_types=1);

namespace PhpImap;

use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PHPUnit\Framework\TestCase;

final class MailboxOAuthTest extends TestCase
{
    /**
     * @var string
     */
    private $imapPath = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';

    /**
     * @var string
     */
    private $login = 'php-imap@example.com';

    /**
     * @var string
     */
    private $password = 'v3rY!53cEt&P4sSWöRd$';

    /**
     * @var string
     */
    private $attachmentsDir = '.';

    /**
     * @var string
     */
    private $serverEncoding = 'UTF-8';

    public function testEnableOAuthUsesAccessTokenForImapConnection(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->enableOAuth('oauth-access-token');

        $this->assertTrue($mailbox->isOAuthEnabled());
        $this->assertSame('oauth-access-token', $mailbox->getImapOAuthToken());
        $this->assertSame('oauth-access-token', $mailbox->getImapOpenSecretForTests());
        $this->assertSame($this->password, $mailbox->getImapPassword());
    }

    public function testDisableOAuthFallsBackToPasswordForImapConnection(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->enableOAuth('oauth-access-token');
        $mailbox->disableOAuth();

        $this->assertFalse($mailbox->isOAuthEnabled());
        $this->assertSame($this->password, $mailbox->getImapOpenSecretForTests());
    }

    public function testEnableOAuthRejectsEmptyAccessToken(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('enableOAuth() expects a non-empty OAuth access token.');

        $mailbox->enableOAuth('   ');
    }

    public function testGetImapOpenOptionsFailsClearlyWhenOAuthIsUnsupported(): void
    {
        if (\defined('OP_XOAUTH2')) {
            $this->markTestSkipped('OP_XOAUTH2 is available in this runtime.');
        }

        $mailbox = $this->getMailbox();
        $mailbox->enableOAuth('oauth-access-token');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('OAuth authentication requires an ext-imap build with OP_XOAUTH2 support.');

        $mailbox->getImapOpenOptionsForTests();
    }

    public function testEnableOAuthAddsXoauth2FlagWhenRuntimeSupportsIt(): void
    {
        if (!\defined('OP_XOAUTH2')) {
            $this->markTestSkipped('OP_XOAUTH2 is not available in this runtime.');
        }

        /** @var int $readonlyOption */
        $readonlyOption = \constant('OP_READONLY');
        /** @var int $oauthOption */
        $oauthOption = \constant('OP_XOAUTH2');

        $mailbox = $this->getMailbox();
        $mailbox->setConnectionArgs($readonlyOption);
        $mailbox->enableOAuth('oauth-access-token');

        $this->assertSame($readonlyOption | $oauthOption, $mailbox->getImapOpenOptionsForTests());
    }

    public function testSetConnectionArgsAcceptsXoauth2WhenRuntimeSupportsIt(): void
    {
        if (!\defined('OP_XOAUTH2')) {
            $this->markTestSkipped('OP_XOAUTH2 is not available in this runtime.');
        }

        /** @var int $oauthOption */
        $oauthOption = \constant('OP_XOAUTH2');

        $mailbox = $this->getMailbox();
        $mailbox->setConnectionArgs($oauthOption);

        $this->assertSame($oauthOption, $mailbox->getImapOptions());
    }

    protected function getMailbox(): Fixtures\Mailbox
    {
        return new Fixtures\Mailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            $this->serverEncoding
        );
    }
}
