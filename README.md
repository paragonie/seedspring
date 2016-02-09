# seedspring

[![Build Status](https://travis-ci.org/paragonie/seedspring.svg?branch=master)](https://travis-ci.org/paragonie/seedspring)

Seeded, Deterministic PRNG (based on AES-CTR instead of LCG) (**Requires PHP 7 or [random_compat](https://github.com/paragonie/random_compat)**)

## Security Warning

This is not meant to replace `random_bytes()`. Think of this as an improvement
to `srand()` + `rand()`. **Do not use this for security purposes.**

## Installing

```sh
composer require paragonie/seedspring
```

### Usage

```php
use \ParagonIE\SeedSpring\SeedSpring;

// For example
$sharedSeed = str_repeat("\x80", 16);

$rng = new SeedSpring($sharedSeed);

$data = $rng->getBytes(1024);
$int = $rng->getInt(1, 100);
```

### Motivation

A cryptographically secure pseudorandom number generator, such as `random_int()`,
is wonderful for security purposes. However, there are situations where you need
to generate a deterministic, pseudorandom byte stream on two devices from a
shared seed. (e.g. something like Minecraft's world generator).

PHP's native seedable (and insecure) RNGs, `rand()` and `mt_rand()`, only 
support a 32-bit integer seed, which severely limits the possible outputs. Our
deterministic RNG supports up to 2^128 possible outputs, since it's based on a
128-bit block cipher.

Our implementation uses AES-128-CTR to turn a finite, 128-bit key into an
practically endless stream of random bytes. (It will repeat after 2^132 bytes of
output. You should consider rekeying after 2^66 bytes.)
