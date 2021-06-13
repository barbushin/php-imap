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

use PHPUnit\Framework\TestCase;

class Issue509Test extends TestCase
{
    public const base64 =
    'vsiz58fPvcq0z7HuLiC05MDlx9jB1rzFvK0gsKi758fVtM+02S4NCsDMt7EgwM/AuiC16b7uILq7
    wPvAzCC++L3AtM+02S4NCsDMwM8gvu62u7DUIMfPvcO0wsH2ILHDsd3H1bTPtNkuDQrBprChIMik
    vcMgsPi9xMD7wLi3ziCw7b/rtce++r3AtM+x7j8NCg0KU2VudCBmcm9tIE1haWw8aHR0cHM6Ly9n
    by5taWNyb3NvZnQuY29tL2Z3bGluay8/TGlua0lkPTU1MDk4Nj4gZm9yIFdpbmRvd3MgMTANCg0K
    RnJvbTogQ2xvdWR3b3JrZXJzIEFnZW50cyBLUjxtYWlsdG86am9icy5rckBjbG91ZHdvcmtlcnMu
    Y29tcGFueT4NClNlbnQ6IEZyaWRheSwgTWF5IDIyLCAyMDIwIDg6NDkgUE0NClRvOiBjYXNleWN1
    bGxlbjE3QGhvdG1haWwuY29tPG1haWx0bzpjYXNleWN1bGxlbjE3QGhvdG1haWwuY29tPg0KU3Vi
    amVjdDogQ2xvdWR3b3JrZXJzIENvbXBhbnktIFlvdXIgQXBwbGljYXRpb24NCg0Kvsiz58fPvcq0
    z7HuLg0KtOe757+hvK0gsPjB9sfRIL73uau/oSDB9r/4x9jB1rzFvK0gsKi758fVtM+02S4gwMy/
    oSC068fYILyzuO215biuwNq46SC02cC9sPogsLC9wLTPtNkuDQq/wrbzwM4gwNu+97DJseIgxL+5
    wrTPxry/obytILDtsLTAzCC6uLO7tMIguN68vMH2v6EgtOvH2CC05MfPtMIgsM3AzCDB1r73uavA
    1LTPtNkuILTnu+fAxyC/wMbbt7kgwMzFzbXpwLogxMTHu8XNt84gxvfF0MC7IMXrx9gguN68vMH2
    v6EgtOTH1bTPtNkuIL/CtvPAzsC4t84gv+6/tbXHtMIgvve5q8DMuOcgvu618LyttefB9iDExMe7
    xc2/zSDA/MDaILHiseK3ziDAz8fYvt8gx9W0z7TZLg0KDQqw7bC0wMcgv+WxuCDD5sG3v6Egv+y8
    sb3Dx9i+3yDHz7TCILi4xa0gtOvIrSCzu7/rwMcgvPbAp7ChILP0wLogvPbB2MDUtM+02S4gwKW7
    58DMxq6/oSCw+MH2x9G06yC3ziwgwMzAzCC068fRILHNx8/AxyChsL+tuLAgxcK1tb/NIMD7sdjA
    +8DOILi2wL2wocH8obHAzCDHyr/kx9W0z7TZLg0KDQrAzL+hILTrx9ggvsuw7SCw6L3DtMIgsM3A
    zCDBwbTZsO0gu/2wosfVtM+02S4guN68vMH2IMfPs6q05yAwLjnAr7fOwNS0z7TZLg0Ksd6/qbTC
    ILjFtN4gw8osIDEwwM8gvve5q8DPILO7v6EgUGF5cGFswMyzqiDAusfgILzbsd0sIL/4x8+0wiC5
    5r3EwLi3ziDB9rHetcu0z7TZLiDAzLexIMDPwLsgx9i6uLzMsMWzqiC16b7uuri9xSDA+8DMIMDW
    wLi9xbChv+Q/DQqxw7Hdx9Egu+fH18DMIMDWwLi46SC+8MGmtecgwfq5rsfYwda9yr3Dv8AhILCo
    u+fH1bTPtNkuDQoNCg0KRG9uZ2h5dW4gS2ltDQoNCkNMT1VEV09SS0VSUyBDT01QQU5ZDQoNCsOk
    v+vGwA0KDQpGb246ICs0NCAoMCkgMjA4MDgwNjU3MQ0KDQoxMjggQ2Fubm9uIFdvcmtzaG9wcyBD
    YW5ub24gRHJpdmUNCkUxNCA0QVMgTG9uZG9uDQp3d3cuY2xvdWR3b3JrZXJzLmNvbXBhbnkNCg0K
    DQo=';

    public const sha256 =
        '5656f5f8a872b8989ba3aaecdfbdc6311bf4c5e0219c27b3b004ce83d8ffd6f3';

    public function testDecode(): void
    {
        $mailbox = new Mailbox('', '', '');

        $mailbox->decodeMimeStrDefaultCharset = 'EUC-KR';
        $decoded = $mailbox->decodeMimeStr(\base64_decode(self::base64));

        $this->assertSame(self::sha256, \hash('sha256', $decoded));
    }
}
