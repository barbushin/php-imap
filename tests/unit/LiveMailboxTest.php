<?php

/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author BAPCLTD-Marv
 */
use ParagonIE\HiddenString\HiddenString;
use PhpImap\Mailbox;
use PHPUnit\Framework\TestCase;

class LiveMailboxTest extends TestCase
{
    const RANDOM_MAILBOX_SAMPLE_SIZE = 3;

    /**
     * Provides constructor arguments for a live mailbox.
     *
     * @return array
     *
     * @psalm-return array{0:HiddenString, 1:HiddenString, 2:HiddenString, 3:string, 4?:string}[]
     *
     * @todo drop php 5.6, add paragonie/hidden-string to require-dev
     */
    public function MailBoxProvider()
    {
        if (!class_exists(HiddenString::class)) {
            $this->markTestSkipped('paragonie/hidden-string not installed!');

            return [];
        }

        $sets = [];

        $imapPath = getenv('PHPIMAP_IMAP_PATH');
        $login = getenv('PHPIMAP_LOGIN');
        $password = getenv('PHPIMAP_PASSWORD');

        if (is_string($imapPath) && is_string($login) && is_string($password)) {
            $sets['CI ENV'] = [new HiddenString($imapPath), new HiddenString($login), new HiddenString($password, true, true), sys_get_temp_dir()];
        }

        return $sets;
    }

    /**
     * @dataProvider MailBoxProvider
     *
     * @param string $attachmentsDir
     * @param string $serverEncoding
     */
    public function testGetImapStream(HiddenString $imapPath, HiddenString $login, HiddenString $password, $attachmentsDir, $serverEncoding = 'UTF-8')
    {
        $mailbox = new Mailbox($imapPath, $login, $password->getString(), $attachmentsDir, $serverEncoding);

        /** @var Exception|null */
        $exception = null;

        try {
            $this->assertTrue(is_resource($mailbox->getImapStream()));
            $this->assertTrue($mailbox->hasImapStream());

            $mailboxes = $mailbox->getMailboxes();
            shuffle($mailboxes);

            $mailboxes = array_values($mailboxes);

            $limit = min(count($mailboxes), self::RANDOM_MAILBOX_SAMPLE_SIZE);

            for ($i = 0; $i < $limit; ++$i) {
                $this->assertTrue(is_array($mailboxes[$i]));
                $this->assertTrue(isset($mailboxes[$i]['shortpath']));
                $this->assertTrue(is_string($mailboxes[$i]['shortpath']));
                $mailbox->switchMailbox($mailboxes[$i]['shortpath']);

                $check = $mailbox->checkMailbox();

                $this->assertTrue(is_object($check));

                foreach ([
                    'Date',
                    'Driver',
                    'Mailbox',
                    'Nmsgs',
                    'Recent',
                ] as $expectedProperty) {
                    $this->assertTrue(property_exists($check, $expectedProperty));
                }

                $this->assertTrue(is_string($check->Date), 'Date property of Mailbox::checkMailbox() result was not a string!');

                $unix = strtotime($check->Date);

                if (false === $unix && preg_match('/[+-]\d{1,2}:?\d{2} \([^\)]+\)$/', $check->Date)) {
                    /** @var int */
                    $pos = strrpos($check->Date, '(');

                    // Although the date property is likely RFC2822-compliant, it will not be parsed by strtotime()
                    $unix = strtotime(substr($check->Date, 0, $pos));
                }

                $this->assertTrue(is_int($unix), 'Date property of Mailbox::checkMailbox() result was not a valid date!');
                $this->assertTrue(in_array($check->Driver, ['POP3', 'IMAP', 'NNTP', 'pop3', 'imap', 'nntp'], true), 'Driver property of Mailbox::checkMailbox() result was not of an expected value!');
                $this->assertTrue(is_int($check->Nmsgs), 'Nmsgs property of Mailbox::checkMailbox() result was not of an expected type!');
                $this->assertTrue(is_int($check->Recent), 'Recent property of Mailbox::checkMailbox() result was not of an expected type!');

                $status = $mailbox->statusMailbox();

                foreach ([
                    'messages',
                    'recent',
                    'unseen',
                    'uidnext',
                    'uidvalidity',
                ] as $expectedProperty) {
                    $this->assertTrue(property_exists($status, $expectedProperty));
                }

                $this->assertSame($check->Nmsgs, $mailbox->countMails(), 'Mailbox::checkMailbox()->Nmsgs did not match Mailbox::countMails()!');
            }
        } catch (Exception $ex) {
            $exception = $ex;
        } finally {
            $mailbox->disconnect();
        }

        if (null !== $exception) {
            throw $exception;
        }
    }
}
