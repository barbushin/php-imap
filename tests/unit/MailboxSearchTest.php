<?php

/**
 * Mailbox search focused unit tests.
 */
declare(strict_types=1);

namespace PhpImap;

use PHPUnit\Framework\TestCase;

final class MailboxSearchTest extends TestCase
{
    public function testSearchMailboxReturnsDirectImapMatchesWithoutSeenSinceFallback(): void
    {
        $mailbox = $this->getSearchTrackingMailbox();
        $criteria = 'SEEN SINCE "28 Nov 2023"';

        $mailbox->searchResultsByCall[$mailbox->getSearchCallKeyForTests($criteria, false)] = [17];

        $this->assertSame([17], $mailbox->searchMailbox($criteria));
        $this->assertSame(
            [
                [
                    'criteria' => $criteria,
                    'disableServerEncoding' => false,
                ],
            ],
            $mailbox->searchCalls
        );
        $this->assertSame([], $mailbox->flagCalls);
    }

    public function testSearchMailboxFallsBackToClientSideSeenFilteringForSimpleSeenSinceCriteria(): void
    {
        $mailbox = $this->getSearchTrackingMailbox();
        $criteria = 'SEEN SINCE "28 Nov 2023" SUBJECT "SEEN order"';
        $fallbackCriteria = 'SINCE "28 Nov 2023" SUBJECT "SEEN order"';

        $mailbox->searchResultsByCall[$mailbox->getSearchCallKeyForTests($criteria, true)] = [];
        $mailbox->searchResultsByCall[$mailbox->getSearchCallKeyForTests($fallbackCriteria, true)] = [11, 12, 13];
        $mailbox->seenByMailId = [
            11 => false,
            12 => true,
            13 => true,
        ];

        $this->assertSame([12, 13], $mailbox->searchMailbox($criteria, true));
        $this->assertSame(
            [
                [
                    'criteria' => $criteria,
                    'disableServerEncoding' => true,
                ],
                [
                    'criteria' => $fallbackCriteria,
                    'disableServerEncoding' => true,
                ],
            ],
            $mailbox->searchCalls
        );
        $this->assertSame(
            [
                [
                    'mailId' => 11,
                    'flag' => '\Seen',
                ],
                [
                    'mailId' => 12,
                    'flag' => '\Seen',
                ],
                [
                    'mailId' => 13,
                    'flag' => '\Seen',
                ],
            ],
            $mailbox->flagCalls
        );
    }

    public function testSearchMailboxDoesNotTreatKeywordSeenArgumentAsSeenCriteria(): void
    {
        $mailbox = $this->getSearchTrackingMailbox();
        $criteria = 'KEYWORD seen SINCE "28 Nov 2023"';

        $this->assertSame([], $mailbox->searchMailbox($criteria));
        $this->assertSame(
            [
                [
                    'criteria' => $criteria,
                    'disableServerEncoding' => false,
                ],
            ],
            $mailbox->searchCalls
        );
        $this->assertSame([], $mailbox->flagCalls);
    }

    /**
     * @return Fixtures\Mailbox&object{
     *     searchCalls: list<array{criteria:string, disableServerEncoding:bool}>,
     *     searchResultsByCall: array<string, list<int>>,
     *     seenByMailId: array<int, bool>,
     *     flagCalls: list<array{mailId:int, flag:string}>
     * }
     */
    private function getSearchTrackingMailbox(): Fixtures\Mailbox
    {
        return new class('', '', '') extends Fixtures\Mailbox {
            /** @var list<array{criteria:string, disableServerEncoding:bool}> */
            public array $searchCalls = [];

            /** @var array<string, list<int>> */
            public array $searchResultsByCall = [];

            /** @var array<int, bool> */
            public array $seenByMailId = [];

            /** @var list<array{mailId:int, flag:string}> */
            public array $flagCalls = [];

            public function getSearchCallKeyForTests(string $criteria, bool $disableServerEncoding): string
            {
                return (string) ((int) $disableServerEncoding).'|'.$criteria;
            }

            /**
             * @return int[]
             *
             * @psalm-return list<int>
             */
            protected function searchMailboxUsingImapSearch(string $criteria, bool $disableServerEncoding): array
            {
                $this->searchCalls[] = [
                    'criteria' => $criteria,
                    'disableServerEncoding' => $disableServerEncoding,
                ];

                return $this->searchResultsByCall[$this->getSearchCallKeyForTests($criteria, $disableServerEncoding)] ?? [];
            }

            public function flagIsSet(int $mailId, string $flag): bool
            {
                $this->flagCalls[] = [
                    'mailId' => $mailId,
                    'flag' => $flag,
                ];

                return $this->seenByMailId[$mailId] ?? false;
            }
        };
    }
}
