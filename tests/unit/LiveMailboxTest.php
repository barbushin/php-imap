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

use function date;
use const ENCBASE64;
use Generator;
use ParagonIE\HiddenString\HiddenString;
use const SORTARRIVAL;
use Throwable;
use const TYPEAPPLICATION;
use const TYPEMULTIPART;
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
 *
 * @todo see @todo of Imap::mail_compose()
 */
class LiveMailboxTest extends AbstractLiveMailboxTest
{
    public const RANDOM_MAILBOX_SAMPLE_SIZE = 3;

    public const ISSUE_EXPECTED_ATTACHMENT_COUNT = [
        448 => 1,
        391 => 2,
    ];

    /**
     * @dataProvider MailBoxProvider
     *
     * @group live
     */
    public function testGetImapStream(HiddenString $imapPath, HiddenString $login, HiddenString $password, string $attachmentsDir, string $serverEncoding = 'UTF-8'): void
    {
        [$mailbox, $remove_mailbox] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        /** @var Throwable|null */
        $exception = null;

        try {
            $mailbox->getImapStream();
            $this->assertTrue($mailbox->hasImapStream());

            $mailboxes = $mailbox->getMailboxes();
            \shuffle($mailboxes);

            $mailboxes = \array_values($mailboxes);

            $limit = \min(\count($mailboxes), self::RANDOM_MAILBOX_SAMPLE_SIZE);

            for ($i = 0; $i < $limit; ++$i) {
                $this->assertIsArray($mailboxes[$i]);
                $this->assertTrue(isset($mailboxes[$i]['shortpath']));
                $this->assertIsString($mailboxes[$i]['shortpath']);
                $mailbox->switchMailbox($mailboxes[$i]['shortpath']);

                $check = $mailbox->checkMailbox();

                foreach ([
                    'Date',
                    'Driver',
                    'Mailbox',
                    'Nmsgs',
                    'Recent',
                ] as $expectedProperty) {
                    $this->assertTrue(\property_exists($check, $expectedProperty));
                }

                $this->assertIsString($check->Date, 'Date property of Mailbox::checkMailbox() result was not a string!');

                $unix = \strtotime($check->Date);

                if (false === $unix && \preg_match('/[+-]\d{1,2}:?\d{2} \([^\)]+\)$/', $check->Date)) {
                    /** @var int */
                    $pos = \strrpos($check->Date, '(');

                    // Although the date property is likely RFC2822-compliant, it will not be parsed by strtotime()
                    $unix = \strtotime(\substr($check->Date, 0, $pos));
                }

                $this->assertIsInt($unix, 'Date property of Mailbox::checkMailbox() result was not a valid date!');
                $this->assertTrue(\in_array($check->Driver, ['POP3', 'IMAP', 'NNTP', 'pop3', 'imap', 'nntp'], true), 'Driver property of Mailbox::checkMailbox() result was not of an expected value!');
                $this->assertIsInt($check->Nmsgs, 'Nmsgs property of Mailbox::checkMailbox() result was not of an expected type!');
                $this->assertIsInt($check->Recent, 'Recent property of Mailbox::checkMailbox() result was not of an expected type!');

                $status = $mailbox->statusMailbox();

                foreach ([
                    'messages',
                    'recent',
                    'unseen',
                    'uidnext',
                    'uidvalidity',
                ] as $expectedProperty) {
                    $this->assertTrue(\property_exists($status, $expectedProperty));
                }

                $this->assertSame($check->Nmsgs, $mailbox->countMails(), 'Mailbox::checkMailbox()->Nmsgs did not match Mailbox::countMails()!');
            }
        } catch (Throwable $ex) {
            $exception = $ex;
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($remove_mailbox);
            $mailbox->disconnect();
        }

        if (null !== $exception) {
            throw $exception;
        }
    }

    /**
     * @psalm-return Generator<int, array{0: array{subject: string}, 1: array{0: array{type: 0|1|3, 'contents.data'?: string, encoding?: 3, subtype?: 'octet-stream', description?: '.gitignore'|'gitignore.', 'disposition.type'?: 'attachment', disposition?: array{filename: '.gitignore'|'gitignore.'}, 'type.parameters'?: array{name: '.gitignore'|'gitignore.'}}, 1?: array{type: 0, 'contents.data': 'test'}, 2?: array{type: 3, encoding: 3, subtype: 'octet-stream', description: 'foo.bin', 'disposition.type': 'attachment', disposition: array{filename: 'foo.bin'}, 'type.parameters': array{name: 'foo.bin'}, 'contents.data': string}, 3?: array{type: 3, encoding: 3, subtype: 'octet-stream', description: 'foo.bin', 'disposition.type': 'attachment', disposition: array{filename: 'foo.bin'}, 'type.parameters': array{name: 'foo.bin'}, 'contents.data': string}}, 2: string}, mixed, void>
     */
    public function ComposeProvider(): Generator
    {
        $random_subject = 'test: '.\bin2hex(\random_bytes(16));

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

        $random_subject = 'barbushin/php-imap#448: dot first:'.\bin2hex(\random_bytes(16));

        yield [
            ['subject' => $random_subject],
            [
                [
                    'type' => TYPEAPPLICATION,
                    'encoding' => ENCBASE64,
                    'subtype' => 'octet-stream',
                    'description' => '.gitignore',
                    'disposition.type' => 'attachment',
                    'disposition' => ['filename' => '.gitignore'],
                    'type.parameters' => ['name' => '.gitignore'],
                    'contents.data' => \base64_encode(
                        \file_get_contents(__DIR__.'/../../.gitignore')
                    ),
                ],
            ],
            (
                'Subject: '.$random_subject."\r\n".
                'MIME-Version: 1.0'."\r\n".
                'Content-Type: APPLICATION/octet-stream; name=.gitignore'."\r\n".
                'Content-Transfer-Encoding: BASE64'."\r\n".
                'Content-Description: .gitignore'."\r\n".
                'Content-Disposition: attachment; filename=.gitignore'."\r\n".
                "\r\n".
                \base64_encode(
                    \file_get_contents(__DIR__.'/../../.gitignore')
                )."\r\n"
            ),
        ];

        $random_subject = 'barbushin/php-imap#448: dot last: '.\bin2hex(\random_bytes(16));

        yield [
            ['subject' => $random_subject],
            [
                [
                    'type' => TYPEAPPLICATION,
                    'encoding' => ENCBASE64,
                    'subtype' => 'octet-stream',
                    'description' => 'gitignore.',
                    'disposition.type' => 'attachment',
                    'disposition' => ['filename' => 'gitignore.'],
                    'type.parameters' => ['name' => 'gitignore.'],
                    'contents.data' => \base64_encode(
                        \file_get_contents(__DIR__.'/../../.gitignore')
                    ),
                ],
            ],
            (
                'Subject: '.$random_subject."\r\n".
                'MIME-Version: 1.0'."\r\n".
                'Content-Type: APPLICATION/octet-stream; name=gitignore.'."\r\n".
                'Content-Transfer-Encoding: BASE64'."\r\n".
                'Content-Description: gitignore.'."\r\n".
                'Content-Disposition: attachment; filename=gitignore.'."\r\n".
                "\r\n".
                \base64_encode(
                    \file_get_contents(__DIR__.'/../../.gitignore')
                )."\r\n"
            ),
        ];

        $random_subject = 'barbushin/php-imap#391: '.\bin2hex(\random_bytes(16));

        $random_attachment_a = \base64_encode(\random_bytes(16));
        $random_attachment_b = \base64_encode(\random_bytes(16));

        yield [
            ['subject' => $random_subject],
            [
                [
                    'type' => TYPEMULTIPART,
                ],
                [
                    'type' => TYPETEXT,
                    'contents.data' => 'test',
                ],
                [
                    'type' => TYPEAPPLICATION,
                    'encoding' => ENCBASE64,
                    'subtype' => 'octet-stream',
                    'description' => 'foo.bin',
                    'disposition.type' => 'attachment',
                    'disposition' => ['filename' => 'foo.bin'],
                    'type.parameters' => ['name' => 'foo.bin'],
                    'contents.data' => $random_attachment_a,
                ],
                [
                    'type' => TYPEAPPLICATION,
                    'encoding' => ENCBASE64,
                    'subtype' => 'octet-stream',
                    'description' => 'foo.bin',
                    'disposition.type' => 'attachment',
                    'disposition' => ['filename' => 'foo.bin'],
                    'type.parameters' => ['name' => 'foo.bin'],
                    'contents.data' => $random_attachment_b,
                ],
            ],
            (
                'Subject: '.$random_subject."\r\n".
                'MIME-Version: 1.0'."\r\n".
                'Content-Type: MULTIPART/MIXED; BOUNDARY="{{REPLACE_BOUNDARY_HERE}}"'."\r\n".
                "\r\n".
                '--{{REPLACE_BOUNDARY_HERE}}'."\r\n".
                'Content-Type: TEXT/PLAIN; CHARSET=US-ASCII'."\r\n".
                "\r\n".
                'test'."\r\n".
                '--{{REPLACE_BOUNDARY_HERE}}'."\r\n".
                'Content-Type: APPLICATION/octet-stream; name=foo.bin'."\r\n".
                'Content-Transfer-Encoding: BASE64'."\r\n".
                'Content-Description: foo.bin'."\r\n".
                'Content-Disposition: attachment; filename=foo.bin'."\r\n".
                "\r\n".
                $random_attachment_a."\r\n".
                '--{{REPLACE_BOUNDARY_HERE}}'."\r\n".
                'Content-Type: APPLICATION/octet-stream; name=foo.bin'."\r\n".
                'Content-Transfer-Encoding: BASE64'."\r\n".
                'Content-Description: foo.bin'."\r\n".
                'Content-Disposition: attachment; filename=foo.bin'."\r\n".
                "\r\n".
                $random_attachment_b."\r\n".
                '--{{REPLACE_BOUNDARY_HERE}}--'."\r\n"
            ),
        ];
    }

    /**
     * @dataProvider ComposeProvider
     *
     * @group compose
     *
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     */
    public function testMailCompose(array $envelope, array $body, string $expected_result): void
    {
        $actual_result = Imap::mail_compose($envelope, $body);

        $expected_result = $this->ReplaceBoundaryHere(
            $expected_result,
            $actual_result
        );

        $this->assertSame($expected_result, $actual_result);
    }

    /**
     * @dataProvider AppendProvider
     *
     * @group live
     *
     * @depends testAppend
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     */
    public function testAppendNudgesMailboxCount(
        array $mailbox_args,
        array $envelope,
        array $body,
        string $_expected_compose_result,
        bool $pre_compose
    ): void {
        if ($this->MaybeSkipAppendTest($envelope)) {
            return;
        }

        [$search_criteria] = $this->SubjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $remove_mailbox, $path] = $this->getMailboxFromArgs(
            $mailbox_args
        );

        $count = $mailbox->countMails();

        $message = [$envelope, $body];

        if ($pre_compose) {
            $message = Imap::mail_compose($envelope, $body);
        }

        $search = $mailbox->searchMailbox($search_criteria);

        $this->assertCount(
            0,
            $search,
            (
                'If a subject was found,'.
                ' then the message is insufficiently unique to assert that'.
                ' a newly-appended message was actually created.'
            )
        );

        $mailbox->appendMessageToMailbox($message);

        $search = $mailbox->searchMailbox($search_criteria);

        $this->assertCount(
            1,
            $search,
            (
                'If a subject was not found, '.
                ' then Mailbox::appendMessageToMailbox() failed'.
                ' despite not throwing an exception.'
            )
        );

        $this->assertSame(
            $count + 1,
            $mailbox->countMails(),
            (
                'If the message count did not increase'.
                ' then either the message was not appended,'.
                ' or a mesage was removed while the test was running.'
            )
        );

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        $mailbox->switchMailbox($path->getString());
        $mailbox->deleteMailbox($remove_mailbox);

        $this->assertCount(
            0,
            $mailbox->searchMailbox($search_criteria),
            (
                'If a subject was found,'.
                ' then the message is was not expunged as requested.'
            )
        );
    }

    /**
     * @dataProvider AppendProvider
     *
     * @group live
     *
     * @depends testAppend
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     */
    public function testAppendSingleSearchMatchesSort(
        array $mailbox_args,
        array $envelope,
        array $body,
        string $_expected_compose_result,
        bool $pre_compose
    ): void {
        if ($this->MaybeSkipAppendTest($envelope)) {
            return;
        }

        [$search_criteria] = $this->SubjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $remove_mailbox, $path] = $this->getMailboxFromArgs(
            $mailbox_args
        );

        $message = [$envelope, $body];

        if ($pre_compose) {
            $message = Imap::mail_compose($envelope, $body);
        }

        $search = $mailbox->searchMailbox($search_criteria);

        $this->assertCount(
            0,
            $search,
            (
                'If a subject was found,'.
                ' then the message is insufficiently unique to assert that'.
                ' a newly-appended message was actually created.'
            )
        );

        $mailbox->appendMessageToMailbox($message);

        $search = $mailbox->searchMailbox($search_criteria);

        $this->assertCount(
            1,
            $search,
            (
                'If a subject was not found, '.
                ' then Mailbox::appendMessageToMailbox() failed'.
                ' despite not throwing an exception.'
            )
        );

        $this->assertSame(
            $search,
            $mailbox->sortMails(SORTARRIVAL, true, $search_criteria)
        );

        $this->assertSame(
            $search,
            $mailbox->sortMails(SORTARRIVAL, false, $search_criteria)
        );

        $this->assertSame(
            $search,
            $mailbox->sortMails(SORTARRIVAL, false, $search_criteria, 'UTF-8')
        );

        $this->assertTrue(\in_array(
            $search[0],
            $mailbox->sortMails(SORTARRIVAL, false, null),
            true
        ));

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        $mailbox->switchMailbox($path->getString());
        $mailbox->deleteMailbox($remove_mailbox);

        $this->assertCount(
            0,
            $mailbox->searchMailbox($search_criteria),
            (
                'If a subject was found,'.
                ' then the message is was not expunged as requested.'
            )
        );
    }

    /**
     * @dataProvider AppendProvider
     *
     * @group live
     *
     * @depends testAppend
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     */
    public function testAppendRetrievalMatchesExpected(
        array $mailbox_args,
        array $envelope,
        array $body,
        string $expected_compose_result,
        bool $pre_compose
    ): void {
        if ($this->MaybeSkipAppendTest($envelope)) {
            return;
        }

        [$search_criteria, $search_subject] = $this->SubjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $remove_mailbox, $path] = $this->getMailboxFromArgs(
            $mailbox_args
        );

        $message = [$envelope, $body];

        if ($pre_compose) {
            $message = Imap::mail_compose($envelope, $body);
        }

        $search = $mailbox->searchMailbox($search_criteria);

        $this->assertCount(
            0,
            $search,
            (
                'If a subject was found,'.
                ' then the message is insufficiently unique to assert that'.
                ' a newly-appended message was actually created.'
            )
        );

        $mailbox->appendMessageToMailbox($message);

        $search = $mailbox->searchMailbox($search_criteria);

        $this->assertCount(
            1,
            $search,
            (
                'If a subject was not found, '.
                ' then Mailbox::appendMessageToMailbox() failed'.
                ' despite not throwing an exception.'
            )
        );

        $actual_result = $mailbox->getMailMboxFormat($search[0]);

        $this->assertSame(
            $this->ReplaceBoundaryHere(
                $expected_compose_result,
                $actual_result
            ),
            $actual_result
        );

        $actual_result = $mailbox->getRawMail($search[0]);

        $this->assertSame(
            $this->ReplaceBoundaryHere(
                $expected_compose_result,
                $actual_result
            ),
            $actual_result
        );

        $mail = $mailbox->getMail($search[0], false);

        $this->assertSame(
            $search_subject,
            $mail->subject,
            (
                'If a retrieved mail did not have a matching subject'.
                ' despite being found via search,'.
                ' then something has gone wrong.'
            )
        );

        $info = $mailbox->getMailsInfo($search);

        $this->assertCount(1, $info);

        $this->assertSame(
            $search_subject,
            $info[0]->subject,
            (
                'If a retrieved mail did not have a matching subject'.
                ' despite being found via search,'.
                ' then something has gone wrong.'
            )
        );

        if (1 === \preg_match(
            '/^barbushin\/php-imap#(448|391):/',
            $envelope['subject'] ?? '',
            $matches
        )) {
            $this->assertTrue($mail->hasAttachments());

            $attachments = $mail->getAttachments();

            $this->assertCount(self::ISSUE_EXPECTED_ATTACHMENT_COUNT[
                (int) $matches[1]],
                $attachments
            );

            if ('448' === $matches[1]) {
                $this->assertSame(
                    \file_get_contents(__DIR__.'/../../.gitignore'),
                    \current($attachments)->getContents()
                );
            }
        }

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        $mailbox->switchMailbox($path->getString());
        $mailbox->deleteMailbox($remove_mailbox);

        $this->assertCount(
            0,
            $mailbox->searchMailbox($search_criteria),
            (
                'If a subject was found,'.
                ' then the message is was not expunged as requested.'
            )
        );
    }

    /**
     * @param string $expected_result
     * @param string $actual_result
     *
     * @return string
     *
     * @psalm-pure
     */
    protected function ReplaceBoundaryHere(
        $expected_result,
        $actual_result
    ) {
        if (
            1 === \preg_match('/{{REPLACE_BOUNDARY_HERE}}/', $expected_result) &&
            1 === \preg_match(
                '/Content-Type: MULTIPART\/MIXED; BOUNDARY="([^"]+)"/',
                $actual_result,
                $matches
            )
        ) {
            $expected_result = \str_replace(
                '{{REPLACE_BOUNDARY_HERE}}',
                $matches[1],
                $expected_result
            );
        }

        return $expected_result;
    }
}
