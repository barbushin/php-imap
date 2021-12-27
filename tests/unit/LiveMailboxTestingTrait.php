<?php
/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author BAPCLTD-Marv
 */
declare(strict_types=1);

namespace PhpImap;

use ParagonIE\HiddenString\HiddenString;

/**
 * @psalm-type MAILBOX_ARGS = array{
 *	0:HiddenString,
 *	1:HiddenString,
 *	2:HiddenString,
 *	3:string,
 *	4?:string
 * }
 */
trait LiveMailboxTestingTrait
{
    /**
     * Provides constructor arguments for a live mailbox.
     *
     * @psalm-return array{'CI ENV'?: array{0: \ParagonIE\HiddenString\HiddenString, 1: \ParagonIE\HiddenString\HiddenString, 2: \ParagonIE\HiddenString\HiddenString, 3: string}}
     *
     * @return (\ParagonIE\HiddenString\HiddenString|string)[][]
     */
    public function MailBoxProvider(): array
    {
        $sets = [];

        $imapPath = \getenv('PHPIMAP_IMAP_PATH');
        $login = \getenv('PHPIMAP_LOGIN');
        $password = \getenv('PHPIMAP_PASSWORD');

        if (\is_string($imapPath) && \is_string($login) && \is_string($password)) {
            $sets['CI ENV'] = [new HiddenString($imapPath), new HiddenString($login), new HiddenString($password, true, true), \sys_get_temp_dir()];
        }

        return $sets;
    }

    /**
     * Get instance of Mailbox, pre-set to a random mailbox.
     *
     * @param string $attachmentsDir
     * @param string $serverEncoding
     *
     * @return (Mailbox|\ParagonIE\HiddenString\HiddenString|string)[]
     *
     * @psalm-return array{0: Mailbox, 1: string, 2: \ParagonIE\HiddenString\HiddenString}
     */
    protected function getMailbox(HiddenString $imapPath, HiddenString $login, HiddenString $password, $attachmentsDir, $serverEncoding = 'UTF-8'): array
    {
        $mailbox = new Mailbox($imapPath->getString(), $login->getString(), $password->getString(), $attachmentsDir, $serverEncoding);

        $random = 'test-box-'.\date('c').\bin2hex(\random_bytes(4));

        $mailbox->createMailbox($random);

        $mailbox->switchMailbox($random, false);

        return [$mailbox, $random, $imapPath];
    }

    /**
     * @psalm-param MAILBOX_ARGS $mailbox_args
     *
     * @return mixed[]
     *
     * @psalm-return array{0:Mailbox, 1:string, 2:HiddenString}
     */
    protected function getMailboxFromArgs(array $mailbox_args): array
    {
        [$path, $username, $password, $attachments_dir] = $mailbox_args;

        return $this->getMailbox(
            $path,
            $username,
            $password,
            $attachments_dir,
            $mailbox_args[4] ?? 'UTF-8'
        );
    }
}
