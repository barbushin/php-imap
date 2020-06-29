<?php
/**
* @author BAPCLTD-Marv
*/
declare(strict_types=1);

namespace PhpImap;

use Generator;
use ParagonIE\HiddenString\HiddenString;
use PHPUnit\Framework\TestCase as Base;
use Throwable;
use UnexpectedValueException;

/**
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
    /**
     * @psalm-return Generator<int|string, array{
     *  0:class-string<Throwable>,
     *  1:string,
     *  2:PSALM_OPEN_ARGS,
     *  3?:bool
     * }>
     */
    public function OpenFailure(): Generator
    {
        yield 'empty mailbox/username/password' => [
            UnexpectedValueException::class,
            'IMAP error:Can\'t open mailbox : no such mailbox',
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
                UnexpectedValueException::class,
                'IMAP error:[AUTHENTICATIONFAILED] Authentication failed.',
                [
                    new HiddenString($imapPath, true, true),
                    new HiddenString($login, true, true),
                    new HiddenString(\strrev($password), true, true),
                    0,
                    0,
                    [],
                ],
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
}
