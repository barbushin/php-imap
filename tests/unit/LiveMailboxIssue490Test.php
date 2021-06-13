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

use Exception;
use ParagonIE\HiddenString\HiddenString;
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
 */
class LiveMailboxIssue490Test extends AbstractLiveMailboxTest
{
    /**
     * @dataProvider MailBoxProvider
     *
     * @group live
     * @group live-issue-490
     */
    public function testGetTextAttachments(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $remove_mailbox] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        $exception = null;

        try {
            $envelope = [
                'subject' => 'barbushin/php-imap#501: '.\bin2hex(\random_bytes(16)),
            ];

            [$search_criteria] = $this->SubjectSearchCriteriaAndSubject(
                $envelope
            );

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

            $message = Imap::mail_compose(
                $envelope,
                [
                    [
                        'type' => TYPEMULTIPART,
                    ],
                    [
                        'type' => TYPETEXT,
                        'contents.data' => 'foo',
                    ],
                    [
                        'type' => TYPEMULTIPART,
                        'subtype' => 'plain',
                        'description' => 'bar.txt',
                        'disposition.type' => 'attachment',
                        'disposition' => ['filename' => 'bar.txt'],
                        'type.parameters' => ['name' => 'bar.txt'],
                        'contents.data' => 'bar',
                    ],
                    [
                        'type' => TYPEMULTIPART,
                        'subtype' => 'plain',
                        'description' => 'baz.txt',
                        'disposition.type' => 'attachment',
                        'disposition' => ['filename' => 'baz.txt'],
                        'type.parameters' => ['name' => 'baz.txt'],
                        'contents.data' => 'baz',
                    ],
                ]
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

            $mail = $mailbox->getMail($search[0], false);

            $this->assertSame('foo', $mail->textPlain);

            $attachments = $mail->getAttachments();
            $keys = \array_keys($attachments);

            $this->assertCount(2, $attachments);

            $this->assertSame('bar', $attachments[$keys[0]]->getContents());
            $this->assertSame('baz', $attachments[$keys[1]]->getContents());
        } catch (Exception $ex) {
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
}
