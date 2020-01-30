<?php

/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author BAPCLTD-Marv
 */

namespace PhpImap;

use Exception;
use Generator;
use ParagonIE\HiddenString\HiddenString;
use PHPUnit\Framework\TestCase;
use function random_int;
use const TYPETEXT;
use function usleep;

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
 * @todo drop php 5.6, remove paragonie/random_compat
 */
class LiveMailboxTest extends TestCase
{
    const RANDOM_MAILBOX_SAMPLE_SIZE = 3;

    /**
     * Provides constructor arguments for a live mailbox.
     *
     * @psalm-return MAILBOX_ARGS[]
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

        if (\is_string($imapPath) && \is_string($login) && \is_string($password)) {
            $sets['CI ENV'] = [new HiddenString($imapPath), new HiddenString($login), new HiddenString($password, true, true), sys_get_temp_dir()];
        }

        return $sets;
    }

    /**
     * @dataProvider MailBoxProvider
     *
     * @param string $attachmentsDir
     * @param string $serverEncoding
     *
     * @return void
     */
    public function testGetImapStream(HiddenString $imapPath, HiddenString $login, HiddenString $password, $attachmentsDir, $serverEncoding = 'UTF-8')
    {
        $mailbox = new Mailbox($imapPath->getString(), $login->getString(), $password->getString(), $attachmentsDir, $serverEncoding);

        /** @var Exception|null */
        $exception = null;

        try {
            $this->assertTrue(\is_resource($mailbox->getImapStream()));
            $this->assertTrue($mailbox->hasImapStream());

            $mailboxes = $mailbox->getMailboxes();
            shuffle($mailboxes);

            $mailboxes = array_values($mailboxes);

            $limit = min(\count($mailboxes), self::RANDOM_MAILBOX_SAMPLE_SIZE);

            for ($i = 0; $i < $limit; ++$i) {
                static::assertTrue(\is_array($mailboxes[$i]));
                static::assertTrue(isset($mailboxes[$i]['shortpath']));
                static::assertTrue(\is_string($mailboxes[$i]['shortpath']));
                $mailbox->switchMailbox($mailboxes[$i]['shortpath']);

                $check = $mailbox->checkMailbox();

                foreach ([
                    'Date',
                    'Driver',
                    'Mailbox',
                    'Nmsgs',
                    'Recent',
                ] as $expectedProperty) {
                    $this->assertTrue(property_exists($check, $expectedProperty));
                }

                $this->assertTrue(\is_string($check->Date), 'Date property of Mailbox::checkMailbox() result was not a string!');

                $unix = strtotime($check->Date);

                if (false === $unix && preg_match('/[+-]\d{1,2}:?\d{2} \([^\)]+\)$/', $check->Date)) {
                    /** @var int */
                    $pos = strrpos($check->Date, '(');

                    // Although the date property is likely RFC2822-compliant, it will not be parsed by strtotime()
                    $unix = strtotime(substr($check->Date, 0, $pos));
                }

                $this->assertTrue(\is_int($unix), 'Date property of Mailbox::checkMailbox() result was not a valid date!');
                $this->assertTrue(\in_array($check->Driver, ['POP3', 'IMAP', 'NNTP', 'pop3', 'imap', 'nntp'], true), 'Driver property of Mailbox::checkMailbox() result was not of an expected value!');
                $this->assertTrue(\is_int($check->Nmsgs), 'Nmsgs property of Mailbox::checkMailbox() result was not of an expected type!');
                $this->assertTrue(\is_int($check->Recent), 'Recent property of Mailbox::checkMailbox() result was not of an expected type!');

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

    /**
     * @psalm-return Generator<int, array{0:COMPOSE_ENVELOPE, 1:COMPOSE_BODY, 2:string}, mixed, void>
     */
    public function ComposeProvider()
    {
        $random_subject = 'test: '.bin2hex(random_bytes(16));

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

        $random_subject = 'barbushin/php-imap#448: dot first:'.bin2hex(random_bytes(16));

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
                    'contents.data' => base64_encode(
                        file_get_contents(__DIR__.'/../../.gitignore')
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
                base64_encode(
                    file_get_contents(__DIR__.'/../../.gitignore')
                )."\r\n"
            ),
        ];

        $random_subject = 'barbushin/php-imap#448: dot last: '.bin2hex(random_bytes(16));

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
                    'contents.data' => base64_encode(
                        file_get_contents(__DIR__.'/../../.gitignore')
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
                base64_encode(
                    file_get_contents(__DIR__.'/../../.gitignore')
                )."\r\n"
            ),
        ];
    }

    /**
     * @dataProvider ComposeProvider
     *
     * @param string $expected_result
     *
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     *
     * @return void
     */
    public function test_mail_compose(array $envelope, array $body, $expected_result)
    {
        static::assertSame($expected_result, Imap::mail_compose($envelope, $body));
    }

    /**
     * @psalm-return Generator<int, array{
     *	0:MAILBOX_ARGS,
     *	1:COMPOSE_ENVELOPE,
     *	2:COMPOSE_BODY,
     *	3:string,
     *	4:bool
     * }, mixed, void>
     */
    public function AppendProvider()
    {
        foreach ($this->MailBoxProvider() as $mailbox_args) {
            foreach ($this->ComposeProvider() as $compose_args) {
                list($envelope, $body, $expected_compose_result) = $compose_args;

                yield [$mailbox_args, $envelope, $body, $expected_compose_result, false];
            }

            foreach ($this->ComposeProvider() as $compose_args) {
                list($envelope, $body, $expected_compose_result) = $compose_args;

                yield [$mailbox_args, $envelope, $body, $expected_compose_result, false];
                yield [$mailbox_args, $envelope, $body, $expected_compose_result, true];
            }
        }
    }

    /**
     * @dataProvider AppendProvider
     *
     * @depends testGetImapStream
     * @depends test_mail_compose
     *
     * @param string $expected_compose_result
     * @param bool   $pre_compose
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     *
     * @return void
     */
    public function test_append(
        array $mailbox_args,
        array $envelope,
        array $body,
        $expected_compose_result,
        $pre_compose
    ) {
        if (!isset($envelope['subject'])) {
            static::markTestSkipped(
                'Cannot search for message by subject, no subject specified!'
            );

            return;
        }

        usleep(random_int(100, 1000));

        static::assertTrue(\is_string(isset($envelope['subject']) ? $envelope['subject'] : null));

        list($path, $username, $password, $attachments_dir) = $mailbox_args;

        $mailbox = new Mailbox(
            $path->getString(),
            $username->getString(),
            $password->getString(),
            $attachments_dir,
            isset($mailbox_args[4]) ? $mailbox_args[4] : 'UTF-8'
        );

        $search_criteria = sprintf('SUBJECT "%s"', $envelope['subject']);

        $search = $mailbox->searchMailbox($search_criteria);

        static::assertCount(
            0,
            $search,
            (
                'If a subject was found,'.
                ' then the message is insufficiently unique to assert that'.
                ' a newly-appended message was actually created.'
            )
        );

        $message = [$envelope, $body];

        if ($pre_compose) {
            $message = Imap::mail_compose($envelope, $body);
        }

        $mailbox->appendMessageToMailbox($message);

        $search = $mailbox->searchMailbox($search_criteria);

        static::assertCount(
            1,
            $search,
            (
                'If a subject was not found, '.
                ' then Mailbox::appendMessageToMailbox() failed'.
                ' despite not throwing an exception.'
            )
        );

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        static::assertCount(
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
     * @depends test_append
     *
     * @param string $expected_compose_result
     * @param bool   $pre_compose
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     *
     * @return void
     */
    public function test_append_nudges_mailbox_count(
        array $mailbox_args,
        array $envelope,
        array $body,
        $expected_compose_result,
        $pre_compose
    ) {
        if (!isset($envelope['subject'])) {
            static::markTestSkipped(
                'Cannot search for message by subject, no subject specified!'
            );

            return;
        }

        usleep(random_int(100, 1000));

        static::assertTrue(\is_string(isset($envelope['subject']) ? $envelope['subject'] : null));

        list($path, $username, $password, $attachments_dir) = $mailbox_args;

        $mailbox = new Mailbox(
            $path->getString(),
            $username->getString(),
            $password->getString(),
            $attachments_dir,
            isset($mailbox_args[4]) ? $mailbox_args[4] : 'UTF-8'
        );

        $search_criteria = sprintf('SUBJECT "%s"', $envelope['subject']);

        $count = $mailbox->countMails();

        $message = [$envelope, $body];

        if ($pre_compose) {
            $message = Imap::mail_compose($envelope, $body);
        }

        $search = $mailbox->searchMailbox($search_criteria);

        static::assertCount(
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

        static::assertCount(
            1,
            $search,
            (
                'If a subject was not found, '.
                ' then Mailbox::appendMessageToMailbox() failed'.
                ' despite not throwing an exception.'
            )
        );

        static::assertSame(
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

        static::assertCount(
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
     * @depends test_append
     *
     * @param string $expected_compose_result
     * @param bool   $pre_compose
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     *
     * @return void
     */
    public function test_append_single_search_matches_sort(
        array $mailbox_args,
        array $envelope,
        array $body,
        $expected_compose_result,
        $pre_compose
    ) {
        if (!isset($envelope['subject'])) {
            static::markTestSkipped(
                'Cannot search for message by subject, no subject specified!'
            );

            return;
        }

        usleep(random_int(100, 1000));

        static::assertTrue(\is_string(isset($envelope['subject']) ? $envelope['subject'] : null));

        list($path, $username, $password, $attachments_dir) = $mailbox_args;

        $mailbox = new Mailbox(
            $path->getString(),
            $username->getString(),
            $password->getString(),
            $attachments_dir,
            isset($mailbox_args[4]) ? $mailbox_args[4] : 'UTF-8'
        );

        $search_criteria = sprintf('SUBJECT "%s"', $envelope['subject']);

        $message = [$envelope, $body];

        if ($pre_compose) {
            $message = Imap::mail_compose($envelope, $body);
        }

        $search = $mailbox->searchMailbox($search_criteria);

        static::assertCount(
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

        static::assertCount(
            1,
            $search,
            (
                'If a subject was not found, '.
                ' then Mailbox::appendMessageToMailbox() failed'.
                ' despite not throwing an exception.'
            )
        );

        static::assertSame(
            $search,
            $mailbox->sortMails(SORTARRIVAL, true, $search_criteria)
        );

        static::assertSame(
            $search,
            $mailbox->sortMails(SORTARRIVAL, false, $search_criteria)
        );

        static::assertSame(
            $search,
            $mailbox->sortMails(SORTARRIVAL, false, $search_criteria, 'UTF-8')
        );

        static::assertTrue(\in_array(
            $search[0],
            $mailbox->sortMails(SORTARRIVAL, false, null),
            true
        ));

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        static::assertCount(
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
     * @depends test_append
     *
     * @param string $expected_compose_result
     * @param bool   $pre_compose
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     * @psalm-param COMPOSE_ENVELOPE $envelope
     * @psalm-param COMPOSE_BODY $body
     *
     * @return void
     */
    public function test_append_retrieval_matches_expected(
        array $mailbox_args,
        array $envelope,
        array $body,
        $expected_compose_result,
        $pre_compose
    ) {
        if (!isset($envelope['subject'])) {
            static::markTestSkipped(
                'Cannot search for message by subject, no subject specified!'
            );

            return;
        }

        usleep(random_int(100, 1000));

        static::assertTrue(\is_string(isset($envelope['subject']) ? $envelope['subject'] : null));

        list($path, $username, $password, $attachments_dir) = $mailbox_args;

        $mailbox = new Mailbox(
            $path->getString(),
            $username->getString(),
            $password->getString(),
            $attachments_dir,
            isset($mailbox_args[4]) ? $mailbox_args[4] : 'UTF-8'
        );

        $search_criteria = sprintf('SUBJECT "%s"', $envelope['subject']);

        $message = [$envelope, $body];

        if ($pre_compose) {
            $message = Imap::mail_compose($envelope, $body);
        }

        $search = $mailbox->searchMailbox($search_criteria);

        static::assertCount(
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

        static::assertCount(
            1,
            $search,
            (
                'If a subject was not found, '.
                ' then Mailbox::appendMessageToMailbox() failed'.
                ' despite not throwing an exception.'
            )
        );

        static::assertSame(
            $expected_compose_result,
            $mailbox->getMailMboxFormat($search[0])
        );

        static::assertSame(
            $expected_compose_result,
            $mailbox->getRawMail($search[0])
        );

        $mail = $mailbox->getMail($search[0], false);

        static::assertSame(
            $envelope['subject'],
            $mail->subject,
            (
                'If a retrieved mail did not have a matching subject'.
                ' despite being found via search,'.
                ' then something has gone wrong.'
            )
        );

        $info = $mailbox->getMailsInfo($search);

        static::assertCount(1, $info);

        static::assertSame(
            $envelope['subject'],
            $info[0]->subject,
            (
                'If a retrieved mail did not have a matching subject'.
                ' despite being found via search,'.
                ' then something has gone wrong.'
            )
        );

        if (1 === preg_match(
            '/^barbushin\/php-imap#448:/',
            $envelope['subject']
        )) {
            static::assertTrue($mail->hasAttachments());

            $attachments = $mail->getAttachments();

            static::assertCount(1, $attachments);

            static::assertSame(
                file_get_contents(__DIR__.'/../../.gitignore'),
                current($attachments)->getContents()
            );
        }

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        static::assertCount(
            0,
            $mailbox->searchMailbox($search_criteria),
            (
                'If a subject was found,'.
                ' then the message is was not expunged as requested.'
            )
        );
    }
}
