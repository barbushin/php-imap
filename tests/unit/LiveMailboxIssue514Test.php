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

use const ENCBASE64;
use ParagonIE\HiddenString\HiddenString;
use Throwable;
use const TYPEIMAGE;
use const TYPEMULTIPART;
use const TYPETEXT;

/**
 * @psalm-import-type COMPOSE_ENVELOPE from AbstractLiveMailboxTest
 */
class LiveMailboxIssue514Test extends AbstractLiveMailboxTest
{
    /**
     * @dataProvider MailBoxProvider
     *
     * @group live
     * @group issue-514
     */
    public function testEmbed(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        /** @var Throwable|null */
        $exception = null;

        $mailboxDeleted = false;

        /** @psalm-var COMPOSE_ENVELOPE */
        $envelope = [
            'subject' => 'barbushin/php-imap#514--'.\bin2hex(\random_bytes(16)),
        ];

        [$search_criteria] = $this->SubjectSearchCriteriaAndSubject($envelope);

        $body = [
            [
                'type' => TYPEMULTIPART,
            ],
            [
                'type' => TYPETEXT,
                'subtype' => 'plain',
                'contents.data' => 'foo',
            ],
            [
                'type' => TYPETEXT,
                'subtype' => 'html',
                'contents.data' => \implode('', [
                    '<img alt="png" width="5" height="1" src="cid:foo.png">',
                    '<img alt="webp" width="5" height="1" src="cid:foo.webp">',
                ]),
            ],
            [
                'type' => TYPEIMAGE,
                'subtype' => 'png',
                'encoding' => ENCBASE64,
                'id' => 'foo.png',
                'description' => 'foo.png',
                'disposition' => ['filename' => 'foo.png'],
                'disposition.type' => 'inline',
                'type.parameters' => ['name' => 'foo.png'],
                'contents.data' => \base64_encode(
                    \file_get_contents(__DIR__.'/Fixtures/rgbkw5x1.png')
                ),
            ],
            [
                'type' => TYPEIMAGE,
                'subtype' => 'webp',
                'encoding' => ENCBASE64,
                'id' => 'foo.webp',
                'description' => 'foo.webp',
                'disposition' => ['filename' => 'foo.webp'],
                'disposition.type' => 'inline',
                'type.parameters' => ['name' => 'foo.webp'],
                'contents.data' => \base64_encode(
                    \file_get_contents(__DIR__.'/Fixtures/rgbkw5x1.webp')
                ),
            ],
        ];

        $message = Imap::mail_compose(
            $envelope,
            $body
        );

        [$mailbox, $remove_mailbox, $path] = $this->getMailboxFromArgs([
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding,
        ]);

        $result = null;

        try {
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

            $result = $mailbox->getMail($search[0], false);

            /** @var array<string, int> */
            $counts = [];

            foreach ($result->getAttachments() as $attachment) {
                if (!isset($counts[(string) $attachment->contentId])) {
                    $counts[(string) $attachment->contentId] = 0;
                }

                ++$counts[(string) $attachment->contentId];
            }

            $this->assertCount(
                2,
                $counts,
                (
                    'counts should only contain foo.png and foo.webp, found: '.
                    \implode(
                        ', ',
                        \array_keys($counts)
                    )
                )
            );

            foreach ($counts as $cid => $count) {
                $this->assertSame(
                    1,
                    $count,
                    $cid.' had '.(string) $count.', expected 1.'
                );
            }

            $this->assertSame(
                'foo',
                $result->textPlain,
                'plain text body did not match expected result!'
            );

            $embedded = \implode('', [
                '<img alt="png" width="5" height="1" src="',
                'data:image/png; charset=binary;base64, ',
                $body[3]['contents.data'],
                '">',
                '<img alt="webp" width="5" height="1" src="',
                'data:image/webp; charset=binary;base64, ',
                $body[4]['contents.data'],
                '">',
            ]);

            $this->assertSame(
                [
                    'foo.png' => 'cid:foo.png',
                    'foo.webp' => 'cid:foo.webp',
                ],
                $result->getInternalLinksPlaceholders(),
                'Internal link placeholders did not match expected result!'
            );

            $replaced = \implode('', [
                '<img alt="png" width="5" height="1" src="',
                'foo.png',
                '">',
                '<img alt="webp" width="5" height="1" src="',
                'foo.webp',
                '">',
            ]);

            foreach ($result->getAttachments() as $attachment) {
                if ('foo.png' === $attachment->contentId) {
                    $replaced = \str_replace(
                        'foo.png',
                        '/'.\basename($attachment->filePath),
                        $replaced
                    );
                } elseif ('foo.webp' === $attachment->contentId) {
                    $replaced = \str_replace(
                        'foo.webp',
                        '/'.\basename($attachment->filePath),
                        $replaced
                    );
                }
            }

            $this->assertSame(
                $replaced,
                $result->replaceInternalLinks(''),
                'replaced html body did not match expected result!'
            );

            $this->assertSame(
                $body[2]['contents.data'],
                $result->textHtml,
                'unembeded html body did not match expected result!'
            );

            $result->embedImageAttachments();

            $this->assertSame(
                $embedded,
                $result->textHtml,
                'embeded html body did not match expected result!'
            );

            $mailbox->deleteMail($search[0]);
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
