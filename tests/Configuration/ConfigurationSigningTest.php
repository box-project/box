<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box\Configuration;

use function array_unshift;
use const DIRECTORY_SEPARATOR;
use function in_array;
use InvalidArgumentException;
use function KevinGH\Box\FileSystem\touch;
use Phar;

/**
 * @covers \KevinGH\Box\Configuration\Configuration
 * @covers \KevinGH\Box\MapFile
 *
 * @group config
 */
class ConfigurationSigningTest extends ConfigurationTestCase
{
    public function test_the_default_signing_is_sha1(): void
    {
        $this->assertSame(Phar::SHA1, $this->config->getSigningAlgorithm());

        $this->assertNull($this->config->getPrivateKeyPath());
        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertFalse($this->config->promptForPrivateKey());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_algorithm_is_the_default_value(): void
    {
        $this->setConfig([
            'algorithm' => 'SHA1',
        ]);

        $this->assertSame(
            ['The "algorithm" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'algorithm' => null,
        ]);

        $this->assertSame(
            ['The "algorithm" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    /**
     * @dataProvider passFileFreeSigningAlgorithmProvider
     */
    public function test_the_signing_algorithm_can_be_configured(string $algorithm, int $expected): void
    {
        $this->setConfig([
            'algorithm' => $algorithm,
        ]);

        $this->assertSame($expected, $this->config->getSigningAlgorithm());

        if (false === in_array($algorithm, ['SHA1', false], true)) {
            $this->assertSame([], $this->config->getRecommendations());
        }
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_signing_algorithm_provided_must_be_valid(): void
    {
        try {
            $this->setConfig([
                'algorithm' => 'INVALID',
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Expected one of: "MD5", "SHA1", "SHA256", "SHA512", "OPENSSL". Got: "INVALID"',
                $exception->getMessage(),
            );
        }
    }

    public function test_the_openssl_algorithm_requires_a_private_key(): void
    {
        try {
            $this->setConfig([
                'algorithm' => 'OPENSSL',
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Expected to have a private key for OpenSSL signing but none have been provided.',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @dataProvider passFileFreeSigningAlgorithmProvider
     */
    public function test_it_generates_a_warning_when_a_key_pass_is_provided_but_the_algorithm_is_not__open_ssl(string $algorithm): void
    {
        $this->setConfig([
            'algorithm' => $algorithm,
            'key-pass' => true,
        ]);

        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertFalse($this->config->promptForPrivateKey());

        if (false === in_array($algorithm, ['SHA1', false], true)) {
            $this->assertSame([], $this->config->getRecommendations());
        }
        $this->assertSame(
            [
                'A prompt for password for the private key has been requested but ignored since the signing algorithm used is not "OPENSSL.',
                'The setting "key-pass" has been set but ignored the signing algorithm is not "OPENSSL".',
            ],
            $this->config->getWarnings(),
        );

        foreach ([false, null] as $keyPass) {
            $this->setConfig([
                'algorithm' => $algorithm,
                'key-pass' => $keyPass,
            ]);

            $this->assertNull($this->config->getPrivateKeyPassphrase());
            $this->assertFalse($this->config->promptForPrivateKey());

            $expectedRecommendation = [
                'The setting "key-pass" has been set but is unnecessary since the signing algorithm is not "OPENSSL".',
            ];

            if (null === $keyPass) {
                array_unshift(
                    $expectedRecommendation,
                    'The "key-pass" setting can be omitted since is set to its default value',
                );
            }

            if (in_array($algorithm, ['SHA1', false], true)) {
                array_unshift(
                    $expectedRecommendation,
                    'The "algorithm" setting can be omitted since is set to its default value',
                );
            }

            $this->assertSame($expectedRecommendation, $this->config->getRecommendations());
            $this->assertSame([], $this->config->getWarnings());
        }

        $this->setConfig([
            'algorithm' => $algorithm,
            'key-pass' => 'weak-password',
        ]);

        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertFalse($this->config->promptForPrivateKey());

        if (false === in_array($algorithm, ['SHA1', false], true)) {
            $this->assertSame([], $this->config->getRecommendations());
        }
        $this->assertSame(
            ['The setting "key-pass" has been set but ignored the signing algorithm is not "OPENSSL".'],
            $this->config->getWarnings(),
        );
    }

    /**
     * @dataProvider passFileFreeSigningAlgorithmProvider
     */
    public function test_it_generates_a_warning_when_a_key_path_is_provided_but_the_algorithm_is_not__open_ssl(string $algorithm): void
    {
        touch('key-file');

        $this->setConfig([
            'algorithm' => $algorithm,
            'key' => 'key-file',
        ]);

        $this->assertNull($this->config->getPrivateKeyPath());

        if (false === in_array($algorithm, ['SHA1', false], true)) {
            $this->assertSame([], $this->config->getRecommendations());
        }
        $this->assertSame(
            ['The setting "key" has been set but is ignored since the signing algorithm is not "OPENSSL".'],
            $this->config->getWarnings(),
        );

        $this->setConfig([
            'algorithm' => $algorithm,
            'key' => null,
        ]);

        $this->assertNull($this->config->getPrivateKeyPath());

        $expectedRecommendation = [
            'The setting "key" has been set but is unnecessary since the signing algorithm is not "OPENSSL".',
        ];

        if (in_array($algorithm, ['SHA1', false], true)) {
            array_unshift(
                $expectedRecommendation,
                'The "algorithm" setting can be omitted since is set to its default value',
            );
        }

        $this->assertSame($expectedRecommendation, $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_key_can_be_configured(): void
    {
        touch('key-file');

        $this->setConfig([
            'algorithm' => 'OPENSSL',
            'key' => 'key-file',
        ]);

        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'key-file', $this->config->getPrivateKeyPath());
        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertFalse($this->config->promptForPrivateKey());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_key_pass_can_be_configured(): void
    {
        touch('key-file');

        $this->setConfig([
            'algorithm' => 'OPENSSL',
            'key' => 'key-file',
            'key-pass' => true,
        ]);

        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'key-file', $this->config->getPrivateKeyPath());
        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertTrue($this->config->promptForPrivateKey());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        foreach ([false, null] as $keyPass) {
            $this->setConfig([
                'algorithm' => 'OPENSSL',
                'key' => 'key-file',
                'key-pass' => $keyPass,
            ]);

            $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'key-file', $this->config->getPrivateKeyPath());
            $this->assertNull($this->config->getPrivateKeyPassphrase());
            $this->assertFalse($this->config->promptForPrivateKey());

            if (null === $keyPass) {
                $this->assertSame(
                    ['The "key-pass" setting can be omitted since is set to its default value'],
                    $this->config->getRecommendations(),
                );
            }

            $this->assertSame([], $this->config->getWarnings());
        }

        $this->setConfig([
            'algorithm' => 'OPENSSL',
            'key' => 'key-file',
            'key-pass' => 'weak-password',
        ]);

        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'key-file', $this->config->getPrivateKeyPath());
        $this->assertSame('weak-password', $this->config->getPrivateKeyPassphrase());
        $this->assertFalse($this->config->promptForPrivateKey());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public static function passFileFreeSigningAlgorithmProvider(): iterable
    {
        yield ['MD5', Phar::MD5];
        yield ['SHA1', Phar::SHA1];
        yield ['SHA256', Phar::SHA256];
        yield ['SHA512', Phar::SHA512];
    }
}
