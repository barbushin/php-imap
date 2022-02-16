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
use ParagonIE\HiddenString\HiddenString;
use const TYPETEXT;

/**
 * @psalm-type MAILBOX_ARGS = array{
 *	0:HiddenString,
 *	1:HiddenString,
 *	2:HiddenString,
 *	3:string,
 *	4?:string
 * }
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
     * @psalm-return Generator<int, array{0: array{subject: string}, 1: array{0: array{type: 0, 'contents.data': 'test'}}, 2: string}, mixed, void>
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

    /**
     * @dataProvider AppendProvider
     *
     * @group live
     * @group live-issue-250
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     */
    public function testAppend(
        array $mailbox_args,
        array $envelope,
        array $body,
        string $expected_compose_result,
        bool $pre_compose
    ): void {
        parent::testAppend(
            $mailbox_args,
            $envelope,
            $body,
            $expected_compose_result,
            $pre_compose
        );
    }
}
