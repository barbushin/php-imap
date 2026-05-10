<?php

declare(strict_types=1);

namespace PhpImap;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImapSequenceSetTest extends TestCase
{
    /**
     * @return array<string, array{0:string}>
     */
    public function validSequenceSetProvider(): array
    {
        return [
            'wildcard only' => ['*'],
            'numeric range' => ['1:5'],
            'range ending with wildcard' => ['1:*'],
            'range starting with wildcard' => ['*:5'],
            'wildcard range' => ['*:*'],
            'comma separated ids' => ['4,5,6'],
            'comma separated mixed sequence set' => ['2,4:7,9,12:*'],
            'wildcard as comma item' => ['1,*'],
            'wildcard range in sequence set' => ['1,3:*,5'],
        ];
    }

    /**
     * @dataProvider validSequenceSetProvider
     */
    public function testEnsureRangeAcceptsValidSequenceSetsWhenAllowed(string $msgNumber): void
    {
        $this->assertSame($msgNumber, $this->ensureRangeForTests($msgNumber, true));
    }

    public function testEnsureRangeNormalizesSingleMessageIdsWhenSequenceSetsAreAllowed(): void
    {
        $this->assertSame('123:123', $this->ensureRangeForTests('123', true));
    }

    /**
     * @return array<string, array{0:string}>
     */
    public function invalidSequenceSetProvider(): array
    {
        return [
            'empty string' => [''],
            'leading comma' => [',1:5'],
            'trailing comma' => ['1:5,'],
            'double comma' => ['1,,5'],
            'double colon' => ['1::5'],
            'non numeric token' => ['foo'],
            'wildcard in malformed position' => ['2,4:7,9,12:**'],
        ];
    }

    /**
     * @dataProvider invalidSequenceSetProvider
     */
    public function testEnsureRangeRejectsInvalidSequenceSetsWhenAllowed(string $msgNumber): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('did not appear to be a valid message id range or sequence');

        $this->ensureRangeForTests($msgNumber, true);
    }

    public function testEnsureRangeRejectsWildcardsWhenOnlyRangesAreAllowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('did not appear to be a valid message id range');

        $this->ensureRangeForTests('1:*');
    }

    private function ensureRangeForTests(int|string $msgNumber, bool $allowSequence = false): string
    {
        $ensureRange = \Closure::bind(
            static function (int|string $msgNumber, bool $allowSequence): string {
                return Imap::EnsureRange($msgNumber, __METHOD__, 1, $allowSequence);
            },
            null,
            Imap::class
        );

        if (!$ensureRange instanceof \Closure) {
            throw new \RuntimeException('Could not bind EnsureRange() test helper.');
        }

        return $ensureRange($msgNumber, $allowSequence);
    }
}
