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

use Generator;
use const TYPETEXT;

/**
 * @psalm-type COMPOSE_ENVELOPE = array{
 *	subject?:string
 * }
 * @psalm-type COMPOSE_BODY = list<array{
 *	type?:int,
 *	encoding?:int,
 *	charset?:string,
 *	subtype?:string,
 *	description?:string,
 *	disposition?:array{filename:string}
 * }>
 */
class LiveMailboxIssue250Test extends AbstractLiveMailboxTest
{
    /**
     * @psalm-return Generator<int, array{0:COMPOSE_ENVELOPE, 1:COMPOSE_BODY, 2:string}, mixed, void>
     */
    public function ComposeProvider(): Generator
    {
        $random_subject = 'barbushin/php-imap#250 测试: '.\bin2hex(\random_bytes(16));

        yield [
            ['subject' => $random_subject],
            [
                [
                    'type' => TYPETEXT,
                    'contents.data' => 'test',
                ],
            ],
            (
                'Subject: '.$random_subject."\r\n".
                'MIME-Version: 1.0'."\r\n".
                'Content-Type: TEXT/PLAIN; CHARSET=US-ASCII'."\r\n".
                "\r\n".
                'test'."\r\n"
            ),
        ];
    }
}
