<?php
use \ParagonIE\SeedSpring\SeedSpring;

class SeedSpringTest extends PHPUnit_Framework_TestCase
{
    public function testDeterminism()
    {
        $seed = random_bytes(16);

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
}
