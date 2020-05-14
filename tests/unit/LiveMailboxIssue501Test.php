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
class LiveMailboxIssue501Test extends AbstractLiveMailboxTest
{
    /**
     * @dataProvider MailBoxProvider
     *
     * @group live
     * @group live-issue-501
     */
    public function testGetEmptyBody(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        list($mailbox, $remove_mailbox) = $this->getMailbox(
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

            list($search_criteria) = $this->SubjectSearchCriteriaAndSubject(
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

            $mailbox->appendMessageToMailbox(Imap::mail_compose(
                $envelope,
                [
                    [
                        'type' => TYPETEXT,
                        'contents.data' => '',
                    ],
                ]
            ));

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

            $this->assertSame('', $mail->textPlain);
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
