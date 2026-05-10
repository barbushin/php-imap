<?php

/**
 * Mailbox address parsing focused unit tests.
 */
declare(strict_types=1);

namespace PhpImap;

use PHPUnit\Framework\TestCase;
use stdClass;

final class MailboxAddressParsingTest extends TestCase
{
    public function testPossiblyGetEmailAndNameFromRecipientUsesMultibyteSafeLowercasing(): void
    {
        $recipient = new stdClass();
        $recipient->mailbox = 'FÜR.MICH';
        $recipient->host = 'EXAMPLE.DE';

        $mailbox = new Fixtures\Mailbox('', '', '');

        $this->assertSame(
            ['für.mich@example.de', null],
            $mailbox->possiblyGetEmailAndNameFromRecipientForTests($recipient)
        );
    }

    public function testPossiblyGetHostNameAndAddressUsesMultibyteSafeLowercasing(): void
    {
        $sender = new stdClass();
        $sender->mailbox = 'GRÜNE.POST';
        $sender->host = 'EXAMPLE.DE';

        $mailbox = new Fixtures\Mailbox('', '', '');

        $this->assertSame(
            ['EXAMPLE.DE', null, 'grüne.post@example.de'],
            $mailbox->possiblyGetHostNameAndAddressForTests([$sender])
        );
    }

    public function testSearchMailboxFromLowercasesSendersUsingUtf8RegardlessOfInternalEncoding(): void
    {
        $mailbox = new class('', '', '') extends Fixtures\Mailbox {
            /** @var array{disableServerEncoding: bool, criteria: string[]} */
            public array $capturedSearchMailboxArguments = [];

            public function searchMailboxFromWithOrWithoutDisablingServerEncodingForTests(string $criteria, bool $disableServerEncoding, string $sender, string ...$senders): array
            {
                return $this->searchMailboxFromWithOrWithoutDisablingServerEncoding($criteria, $disableServerEncoding, $sender, ...$senders);
            }

            /**
             * @param bool   $disableServerEncoding
             * @param string $single_criteria
             * @param string ...$criteria
             *
             * @return int[]
             */
            protected function searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding($disableServerEncoding, $single_criteria, ...$criteria)
            {
                \array_unshift($criteria, $single_criteria);

                $this->capturedSearchMailboxArguments = [
                    'disableServerEncoding' => $disableServerEncoding,
                    'criteria' => $criteria,
                ];

                return [];
            }
        };

        $previousInternalEncoding = \mb_internal_encoding();

        try {
            \mb_internal_encoding('ISO-8859-1');

            $this->assertSame(
                [],
                $mailbox->searchMailboxFromWithOrWithoutDisablingServerEncodingForTests(
                    'ALL',
                    true,
                    'FÜR@EXAMPLE.DE',
                    'für@example.de',
                    'NOREPLY@EXAMPLE.DE'
                )
            );
        } finally {
            \mb_internal_encoding($previousInternalEncoding);
        }

        $this->assertSame(
            [
                'disableServerEncoding' => true,
                'criteria' => [
                    'ALL FROM für@example.de',
                    'ALL FROM noreply@example.de',
                ],
            ],
            $mailbox->capturedSearchMailboxArguments
        );
    }
}
