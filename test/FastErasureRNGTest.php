<?php
use ParagonIE\SeedSpring\FastErasureRNG;
use ParagonIE\ConstantTime\Binary;
use ParagonIE\ConstantTime\Hex;
use PHPUnit\Framework\TestCase;

/**
 * Class SeedSpringTest
 */
class FastErasureRNGTest extends TestCase
{
    public function keysProvider()
    {
        return [
            [str_repeat("\x00", 32)],
            [str_repeat("\xFF", 32)],
            [random_bytes(32)]
        ];
    }

    /**
     * @dataProvider keysProvider
     */
    public function testFastErasure($key)
    {
        $rnd1 = new FastErasureRNG($key);
        $rnd2 = new FastErasureRNG($key);
        $rnd3 = new FastErasureRNG($key);

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
                Binary::safeStrlen($buf1),
                Binary::safeStrlen($buf2),
                'Not the same length - test ' . $test
            );
            $this->assertNotSame(
                $rnd3->seek($test, SEEK_SET)->getBytes(32),
                $rnd1->getBytes(32),
                'RNG must rekey, but did not'
            );
            $this->assertNotSame(
                $rnd3->seek($test, SEEK_SET)->getBytes(32),
                $rnd2->getBytes(32),
                'RNG must rekey, but did not'
            );
        }
    }
}
