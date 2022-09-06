<?php
use ParagonIE\SeedSpring\SeedSpring;
use ParagonIE\ConstantTime\Hex;
use PHPUnit\Framework\TestCase;

/**
 * Class SeedSpringTest
 */
class SeedSpringTest extends TestCase
{

    public function seedProvider()
    {
        return [
            [str_repeat("\x00", 16)],
            [str_repeat("\x00", 32)],
            [str_repeat("\xFF", 16)],
            [str_repeat("\xFF", 32)],
            [random_bytes(16)],
            [random_bytes(32)]
        ];
    }

    /**
     * @dataProvider seedProvider
     * @throws Exception
     */
    public function testDeterminism($seed)
    {
        $rnd1 = new SeedSpring($seed);
        $rnd2 = new SeedSpring($seed);

        $out1 = $rnd1->getBytes(16);
        $out2 = $rnd1->getBytes(16);
        $out3 = $rnd2->getBytes(32);
        
        $this->assertEquals(
            $out1 . $out2,
            $out3
        );

        $int1 = $rnd1->getInt(0, 255);
        $int2 = $rnd2->getInt(0, 255);
        
        $this->assertEquals(
            $int1,
            $int2
        );
    }

    /**
     * Our nonce logic needs to match OpenSSL's internals.
     *
     * @dataProvider seedProvider
     */
    public function testCtrModeNonce($seed)
    {
        $rnd1 = new SeedSpring($seed);
        $rnd2 = new SeedSpring($seed);

        foreach ([4096, 4097, 8192, 16384, 65536] as $test) {
            $buf1 = '';

            for ($i = 0; $i < $test; $i += 16) {
                $buf1 .= $rnd1->getBytes(
                    $i + 16 > $test
                        ? ($test - $i)
                        : 16
                );
            }
            $buf2 = $rnd2->getBytes($test);
            $this->assertSame(
                \ParagonIE\ConstantTime\Binary::safeStrlen($buf1),
                \ParagonIE\ConstantTime\Binary::safeStrlen($buf2),
                'Not the same length - test ' . $test
            );

            $this->assertSame(
                Hex::encode(\ParagonIE\ConstantTime\Binary::safeSubstr($buf1, 0, 16)),
                Hex::encode(\ParagonIE\ConstantTime\Binary::safeSubstr($buf2, 0, 16)),
                'AES CTR nonce isn\'t correct - first 16 - test ' . $test
            );

            $this->assertSame(
                bin2hex(substr($buf1, 16, 16)),
                bin2hex(substr($buf2, 16, 16)),
                'AES CTR nonce isn\'t correct - next 16 - test ' . $test
            );

            $this->assertSame(
                bin2hex(substr($buf1, -16, 16)),
                bin2hex(substr($buf2, -16, 16)),
                'AES CTR nonce isn\'t correct - last 16 - test ' . $test
            );
        }
    }

    public function testNonceChanges()
    {
        $prng = new SeedSpring('JuxJ1XLnBKk7gPAS');
        $this->assertSame(
            'v',
            $prng->getBytes(1)
        );
        $this->assertNotSame(
            'v',
            $prng->getBytes(1)
        );
    }
}
