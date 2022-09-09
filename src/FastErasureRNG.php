<?php
namespace ParagonIE\SeedSpring;

use ParagonIE\ConstantTime\Binary;
use Exception;

class FastErasureRNG
{
    /**
     * @var SeedSpring $seedspring
     */
    private $seedspring;

    /**
     * @param string $initialSeed
     * @param int $counter
     */
    public function __construct($initialSeed = '', $counter = 0)
    {
        if (Binary::safeStrlen($initialSeed) !== 32) {
            throw new \InvalidArgumentException('Seed must be 16 or 32 bytes');
        }
        $this->seedspring = new SeedSpring($initialSeed, $counter);
    }

    /**
     * Deterministic random byte generator
     *
     * @param int $numBytes How many bytes do we want?
     * @return string
     */
    public function getBytes($numBytes)
    {
        $allBytes = $this->seedspring->getBytes($numBytes + 32);
        $return = Binary::safeSubstr($allBytes, 0, $numBytes);
        $this->seedspring->reseed(
            Binary::safeSubstr($allBytes, $numBytes, 32)
        );
        return $return;
    }

    /**
     * Generate a deterministic random integer
     *
     * Stolen from paragonie/random_compat
     *
     * @param int $min
     * @param int $max
     * @return int
     * @throws Exception
     */
    public function getInt($min, $max)
    {
        $int = $this->seedspring->getInt($min, $max);
        $this->seedspring->reseed($this->seedspring->getBytes(32));
        return $int;
    }

    /**
     * Seek to a given position
     *
     * @param int $position
     * @param int $seektype Set to self:SEEK_SET or self::SEEK_INCREASE
     * @return self
     */
    public function seek($position, $seektype = SeedSpring::SEEK_SET)
    {
        $this->seedspring->seek($position, $seektype);
        return $this;
    }
}
