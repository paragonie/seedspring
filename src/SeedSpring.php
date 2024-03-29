<?php
namespace ParagonIE\SeedSpring;

use ParagonIE\ConstantTime\Binary;

/**
 * Class SeedSpring
 * @package ParagonIE\SeedSpring
 */
final class SeedSpring
{
    const SEEK_SET = 0;

    // Increase the nonce counter
    const SEEK_INCREASE = 1;
    const SEEK_INC = 1;

    /**
     * @var int
     */
    protected $counter;

    /**
     * @var int
     */
    private $keyLength;

    /**
     * @var bool
     */
    protected $usePolyfill;

    /**
     * SeedSpring constructor.
     *
     * @param string $seed
     * @param int $counter
     */
    public function __construct($seed = '', $counter = 0)
    {
        $this->reseed($seed, $counter);
    }

    /**
     * @param string $seed
     * @param int $counter
     * @return self
     */
    public function reseed($seed = '', $counter = 0)
    {
        $length = Binary::safeStrlen($seed);
        if (!\in_array($length, [16, 32], true)) {
            throw new \InvalidArgumentException('Seed must be 16 or 32 bytes');
        }
        $this->keyLength = $length << 3;
        $this->seed('set', $seed);
        $this->counter = 0;
        $this->usePolyfill = !\in_array(
            'aes-' . $this->keyLength . '-ctr',
            \openssl_get_cipher_methods(),
            true
        );
        return $this;
    }

    /**
     * Set/get a seed (purpose: hide it from crash dumps)
     *
     * @param string $action (get|set)
     * @param string $data (for setting)
     * @return string
     * @throws \Error
     * @psalm-suppress MixedArrayAssignment
     * @psalm-suppress UnusedParam
     */
    private function seed($action = 'get', $data = '')
    {
        static $seed = [];
        $hash = \spl_object_hash($this);
        if ($action === 'set') {
            $seed[$hash] = $data;
            return '';
        } elseif ($action === 'get') {
            /**
             * @var array<string, string> $seed
             * @var string $return
             */
            return $seed[$hash];
        }
        throw new \Error('Unknown action');
    }

    /**
     * Seek to a given position
     *
     * @param int $position
     * @param int $seektype Set to self:SEEK_SET or self::SEEK_INCREASE
     * @return self
     */
    public function seek($position, $seektype = self::SEEK_SET)
    {
        switch ($seektype) {
            case self::SEEK_SET:
                $this->counter = $position;
                break;
            case self::SEEK_INCREASE:
                $this->counter += $position;
                break;
        }
        return $this;
    }

    /**
     * Deterministic random byte generator
     *
     * @param int $numBytes How many bytes do we want?
     * @return string
     */
    public function getBytes($numBytes)
    {
        if ($this->usePolyfill) {
            return self::aesCtr(
                \str_repeat("\0", $numBytes),
                $this->seed(),
                $this->getNonce($numBytes),
                $this->keyLength
            );
        }
        return (string) \openssl_encrypt(
            \str_repeat("\0", $numBytes),
            'aes-' . $this->keyLength . '-ctr',
            $this->seed(),
            OPENSSL_RAW_DATA,
            $this->getNonce($numBytes)
        );
    }

    /**
     * Generate a deterministic random integer
     *
     * Stolen from paragonie/random_compat
     *
     * @param int $min
     * @param int $max
     * @return int
     * @throws \Error
     * @throws \Exception
     */
    public function getInt($min, $max)
    {
        /**
         * Now that we've verified our weak typing system has given us an integer,
         * let's validate the logic then we can move forward with generating random
         * integers along a given range.
         */
        if ($min > $max) {
            throw new \Error(
                'Minimum value must be less than or equal to the maximum value'
            );
        }
        if ($max === $min) {
            return $min;
        }

        /**
         * Initialize variables to 0
         *
         * We want to store:
         * $bytes => the number of random bytes we need
         * $mask => an integer bitmask (for use with the &) operator
         *          so we can minimize the number of discards
         *
         * @var int $valueShift
         * @var int $mask
         * @var int $bytes
         * @var int $bits
         * @var int $attempts
         */
        $attempts = $bits = $bytes = $mask = $valueShift = 0;

        /**
         * At this point, $range is a positive number greater than 0. It might
         * overflow, however, if $max - $min > PHP_INT_MAX. PHP will cast it to
         * a float and we will lose some precision.
         * @var int|float $range
         */
        $range = $max - $min;

        /**
         * Test for integer overflow:
         */
        if (!\is_int($range)) {
            /**
             * Still safely calculate wider ranges.
             * Provided by @CodesInChaos, @oittaa
             *
             * @ref https://gist.github.com/CodesInChaos/03f9ea0b58e8b2b8d435
             *
             * We use ~0 as a mask in this case because it generates all 1s
             *
             * @ref https://eval.in/400356 (32-bit)
             * @ref http://3v4l.org/XX9r5  (64-bit)
             */
            $bytes = PHP_INT_SIZE;
            /** @var int $mask */
            $mask = ~0;
        } else {
            /**
             * $bits is effectively ceil(log($range, 2)) without dealing with
             * type juggling
             */
            while ($range > 0) {
                if ($bits % 8 === 0) {
                   ++$bytes;
                }
                ++$bits;
                $range >>= 1;
                $mask = $mask << 1 | 1;
            }
            $valueShift = $min;
        }

        /**
         * Now that we have our parameters set up, let's begin generating
         * random integers until one falls between $min and $max
         */
        do {
            /**
             * The rejection probability is at most 0.5, so this corresponds
             * to a failure probability of 2^-128 for a working RNG
             */
            if ($attempts > 128) {
                throw new \Exception(
                    'RNG is broken - too many rejections'
                );
            }

            /**
             * Let's grab the necessary number of random bytes
             */
            $randomByteString = $this->getBytes($bytes);

            /**
             * Let's turn $randomByteString into an integer
             *
             * This uses bitwise operators (<< and |) to build an integer
             * out of the values extracted from ord()
             *
             * Example: [9F] | [6D] | [32] | [0C] =>
             *   159 + 27904 + 3276800 + 201326592 =>
             *   204631455
             */
            $val = 0;
            for ($i = 0; $i < $bytes; ++$i) {
                $val |= \ord($randomByteString[$i]) << ($i * 8);
            }

            /**
             * Apply mask
             */
            $val &= $mask;
            $val += $valueShift;

            ++$attempts;
            /**
             * If $val overflows to a floating point number,
             * ... or is larger than $max,
             * ... or smaller than $min,
             * then try again.
             */
        } while (!\is_int($val) || $val > $max || $val < $min);
        return $val;
    }

    /**
     * Get (and increment) the nonce for AES-CTR
     *
     * @param int $increment
     * @return string
     */
    protected function getNonce($increment = 0)
    {
        $nonce = '';
        $ctr = $this->counter;
        $incr = (int) \ceil(($increment + ($increment % 16)) / 16);
        $this->counter += $incr;
        while ($ctr > 0) {
            $nonce = \pack('C', $ctr & 0xFF) . $nonce;
            $ctr >>= 8;
        }
        return \str_pad($nonce, 16, "\0", STR_PAD_LEFT);
    }

    /**
     * Userland polyfill for AES-128-CTR, using AES-128-ECB
     *
     * @param string $plaintext
     * @param string $key
     * @param string $nonce
     * @return string
     */
    public static function aes128ctr($plaintext, $key, $nonce)
    {
        return self::aesCtr($plaintext, $key, $nonce, 128);
    }

    /**
     * Userland polyfill for AES-256-CTR, using AES-256-ECB
     *
     * @param string $plaintext
     * @param string $key
     * @param string $nonce
     * @return string
     */
    public static function aes256ctr($plaintext, $key, $nonce)
    {
        return self::aesCtr($plaintext, $key, $nonce, 256);
    }

    /**
     * Userland polyfill for AES-CTR, using AES-ECB
     *
     * @param string $plaintext
     * @param string $key
     * @param string $nonce
     * @param int $keyLength
     * @return string
     */
    private static function aesCtr($plaintext, $key, $nonce, $keyLength)
    {
        if (empty($plaintext)) {
            return '';
        }
        $length = Binary::safeStrlen($plaintext);
        $numBlocks = (($length - 1) >> 4) + 1;
        $stream = '';
        for ($i = 0; $i < $numBlocks; ++$i) {
            $stream .= $nonce;
            $nonce = self::ctrNonceIncrease($nonce);
        }
        /** @var string $xor */
        $xor = \openssl_encrypt(
            $stream,
            'aes-' . $keyLength . '-ecb',
            $key,
            OPENSSL_RAW_DATA
        );
        return (
            $plaintext ^ Binary::safeSubstr($xor, 0, $length)
        );
    }

    /**
     * Increase a counter nonce, starting with the LSB (big-endian)
     *
     * @param string $nonce
     * @return string
     */
    public static function ctrNonceIncrease($nonce)
    {
        /** @var array<int, int> $pieces */
        $pieces = \unpack('C*', $nonce);
        $c = 0;
        ++$pieces[16];
        for ($i = 16; $i > 0; --$i) {
            $pieces[$i] += $c;
            $c = $pieces[$i] >> 8;
            $pieces[$i] &= 0xff;
        }
        \array_unshift($pieces, \str_repeat('C', 16));
        return (string) \call_user_func_array('pack', $pieces);
    }
}
