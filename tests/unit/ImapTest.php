<?php
/**
* @author BAPCLTD-Marv
*/
declare(strict_types=1);

namespace PhpImap;

use Generator;
use ParagonIE\HiddenString\HiddenString;
use PhpImap\Exceptions\ConnectionException;
use PHPUnit\Framework\TestCase as Base;
use const SORTARRIVAL;
use Throwable;

/**
 * @psalm-type MAILBOX_ARGS = array{
 *	0:HiddenString,
 *	1:HiddenString,
 *	2:HiddenString,
 *	3:string,
 *	4?:string
 * }
 * @psalm-type PSALM_OPEN_ARGS = array{
 *  0:HiddenString,
 *  1:HiddenString,
 *  2:HiddenString,
 *  3:int,
 *  4:int,
 *  5:array{DISABLE_AUTHENTICATOR:string}|array<empty, empty>
 * } $args
 */
class ImapTest extends Base
{
    use LiveMailboxTestingTrait;

    /**
     * @psalm-return Generator<'CI ENV with invalid password'|'empty mailbox/username/password', array{0: ConnectionException::class, 1: '/^[AUTHENTICATIONFAILED]/'|'Can't open mailbox : no such mailbox', 2: array{0: HiddenString, 1: HiddenString, 2: HiddenString, 3: 0, 4: 0, 5: array<empty, empty>}, 3?: true}, mixed, void>
     */
    public function OpenFailure(): Generator
    {
        yield 'empty mailbox/username/password' => [
            ConnectionException::class,
            'Can\'t open mailbox : no such mailbox',
            [
                new HiddenString(''),
                new HiddenString(''),
                new HiddenString(''),
                0,
                0,
                [],
            ],
        ];

        $imapPath = \getenv('PHPIMAP_IMAP_PATH');
        $login = \getenv('PHPIMAP_LOGIN');
        $password = \getenv('PHPIMAP_PASSWORD');

        if (\is_string($imapPath) && \is_string($login) && \is_string($password)) {
            yield 'CI ENV with invalid password' => [
                ConnectionException::class,
                '/^\[AUTHENTICATIONFAILED\].*/',
                [
                    new HiddenString($imapPath, true, true),
                    new HiddenString($login, true, true),
                    new HiddenString(\strrev($password), true, true),
                    0,
                    0,
                    [],
                ],
                true,
            ];
        }
    }

    /**
     * @dataProvider OpenFailure
     *
     * @psalm-param class-string<Throwable> $exception
     * @psalm-param PSALM_OPEN_ARGS $args
     */
    public function testOpenFailure(
        string $exception,
        string $message,
        array $args,
        bool $message_as_regex = false
    ): void {
        $this->expectException($exception);

        if ($message_as_regex) {
            $this->expectExceptionMessageMatches($message);
        } else {
            $this->expectExceptionMessage($message);
        }

        Imap::open(
            $args[0]->getString(),
            $args[1]->getString(),
            $args[2]->getString(),
            $args[3],
            $args[4],
            $args[5]
        );
    }

    /**
     * @dataProvider MailBoxProvider
     *
     * @group live
     */
    public function testSortEmpty(
        HiddenString $path,
        HiddenString $login,
        HiddenString $password
    ): void {
        [$mailbox, $remove_mailbox, $path] = $this->getMailboxFromArgs([
            $path,
            $login,
            $password,
            \sys_get_temp_dir(),
        ]);

        /** @var Throwable|null */
        $exception = null;

        $mailboxDeleted = false;

        try {
            $this->assertSame(
                [],
                Imap::sort(
                    $mailbox->getImapStream(),
                    SORTARRIVAL,
                    false,
                    0
                )
            );
        } catch (Throwable $ex) {
            $exception = $ex;
        } finally {
            $mailbox->switchMailbox($path->getString());
            if (!$mailboxDeleted) {
                $mailbox->deleteMailbox($remove_mailbox);
            }
            $mailbox->disconnect();
        }

        if (null !== $exception) {
            throw $exception;
        }
    }
}
