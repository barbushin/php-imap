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

/**
 * @psalm-type MAILBOX_ARGS = array{
 *	0:HiddenString,
 *	1:HiddenString,
 *	2:HiddenString,
 *	3:string,
 *	4?:string
 * }
 */
class LiveMailboxWithManualSetupTest extends AbstractLiveMailboxTest
{
    /**
     * @psalm-return Generator<int, array{0: '.issue-499.Éléments envoyés'}, mixed, void>
     */
    public function RelativeToRootPathProvider(): Generator
    {
        yield [
            '.issue-499.Éléments envoyés',
        ];
    }

    /**
     * @psalm-return Generator<int, array{0: array{0: HiddenString, 1: HiddenString, 2: HiddenString, 3: string, 4?: string}}, mixed, void>
     */
    public function statusProviderAbsolutePath(): Generator
    {
        foreach ($this->RelativeToRootPathProvider() as $path_args) {
            foreach ($this->MailBoxProvider() as $args) {
                $args[0] = new HiddenString($args[0]->getString().$path_args[0]);

                yield [$args];
            }
        }
    }

    /**
     * Tests the status of an absolute mailbox path set from the Mailbox constructor.
     *
     * @dataProvider statusProviderAbsolutePath
     *
     * @group live
     * @group live-manual
     *
     * @psalm-param MAILBOX_ARGS $mailbox_args
     */
    public function testAbsolutePathStatusFromConstruction(
        array $mailbox_args
    ): void {
        [$mailbox] = $this->getMailboxFromArgs($mailbox_args);

        $mailbox->statusMailbox();

        $this->assertTrue(true);
    }
}
