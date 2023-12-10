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

use Fidry\FileSystem\FS;
use InvalidArgumentException;
use KevinGH\Box\MapFile;
use KevinGH\Box\Phar\SigningAlgorithm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use UnexpectedValueException;
use function array_unshift;
use function in_array;
use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
#[CoversClass(Configuration::class)]
#[CoversClass(MapFile::class)]
#[Group('config')]
class ConfigurationSigningTest extends ConfigurationTestCase
{
    public function test_the_default_signing_is_sha512(): void
    {
        self::assertSame(SigningAlgorithm::SHA512, $this->config->getSigningAlgorithm());

        self::assertNull($this->config->getPrivateKeyPath());
        self::assertNull($this->config->getPrivateKeyPassphrase());
        self::assertFalse($this->config->promptForPrivateKey());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_algorithm_is_the_default_value(): void
    {
        $this->setConfig([
            'algorithm' => 'SHA512',
        ]);

        self::assertSame(
            ['The "algorithm" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'algorithm' => null,
        ]);

        self::assertSame(
            ['The "algorithm" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    #[DataProvider('passFileFreeSigningAlgorithmProvider')]
    public function test_the_signing_algorithm_can_be_configured(string $algorithm, SigningAlgorithm $expected): void
    {
        $this->setConfig([
            'algorithm' => $algorithm,
        ]);

        self::assertSame($expected, $this->config->getSigningAlgorithm());

        if (false === in_array($algorithm, ['SHA512', false], true)) {
            self::assertSame([], $this->config->getRecommendations());
        }
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_signing_algorithm_provided_must_be_valid(): void
    {
        $this->expectExceptionObject(
            new UnexpectedValueException(
                'The signing algorithm "INVALID" is not supported by your current PHAR version.',
            ),
        );

        $this->setConfig([
            'algorithm' => 'INVALID',
        ]);
    }

    public function test_the_openssl_algorithm_requires_a_private_key(): void
    {
        try {
            $this->setConfig([
                'algorithm' => 'OPENSSL',
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Expected to have a private key for OpenSSL signing but none have been provided.',
                $exception->getMessage(),
            );
        }
    }

    #[DataProvider('passFileFreeSigningAlgorithmProvider')]
    public function test_it_generates_a_warning_when_a_key_pass_is_provided_but_the_algorithm_is_not__open_ssl(string $algorithm): void
    {
        $this->setConfig([
            'algorithm' => $algorithm,
            'key-pass' => true,
        ]);

        self::assertNull($this->config->getPrivateKeyPassphrase());
        self::assertFalse($this->config->promptForPrivateKey());

        if (false === in_array($algorithm, ['SHA512', false], true)) {
            self::assertSame([], $this->config->getRecommendations());
        }
        self::assertSame(
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

            self::assertNull($this->config->getPrivateKeyPassphrase());
            self::assertFalse($this->config->promptForPrivateKey());

            $expectedRecommendation = [
                'The setting "key-pass" has been set but is unnecessary since the signing algorithm is not "OPENSSL".',
            ];

            if (null === $keyPass) {
                array_unshift(
                    $expectedRecommendation,
                    'The "key-pass" setting can be omitted since is set to its default value',
                );
            }

            if (in_array($algorithm, ['SHA512', false], true)) {
                array_unshift(
                    $expectedRecommendation,
                    'The "algorithm" setting can be omitted since is set to its default value',
                );
            }

            self::assertSame($expectedRecommendation, $this->config->getRecommendations());
            self::assertSame([], $this->config->getWarnings());
        }

        $this->setConfig([
            'algorithm' => $algorithm,
            'key-pass' => 'weak-password',
        ]);

        self::assertNull($this->config->getPrivateKeyPassphrase());
        self::assertFalse($this->config->promptForPrivateKey());

        if (false === in_array($algorithm, ['SHA512', false], true)) {
            self::assertSame([], $this->config->getRecommendations());
        }
        self::assertSame(
            ['The setting "key-pass" has been set but ignored the signing algorithm is not "OPENSSL".'],
            $this->config->getWarnings(),
        );
    }

    #[DataProvider('passFileFreeSigningAlgorithmProvider')]
    public function test_it_generates_a_warning_when_a_key_path_is_provided_but_the_algorithm_is_not__open_ssl(string $algorithm): void
    {
        FS::touch('key-file');

        $this->setConfig([
            'algorithm' => $algorithm,
            'key' => 'key-file',
        ]);

        self::assertNull($this->config->getPrivateKeyPath());

        if (false === in_array($algorithm, ['SHA512', false], true)) {
            self::assertSame([], $this->config->getRecommendations());
        }
        self::assertSame(
            ['The setting "key" has been set but is ignored since the signing algorithm is not "OPENSSL".'],
            $this->config->getWarnings(),
        );

        $this->setConfig([
            'algorithm' => $algorithm,
            'key' => null,
        ]);

        self::assertNull($this->config->getPrivateKeyPath());

        $expectedRecommendation = [
            'The setting "key" has been set but is unnecessary since the signing algorithm is not "OPENSSL".',
        ];

        if (in_array($algorithm, ['SHA512', false], true)) {
            array_unshift(
                $expectedRecommendation,
                'The "algorithm" setting can be omitted since is set to its default value',
            );
        }

        self::assertSame($expectedRecommendation, $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_key_can_be_configured(): void
    {
        FS::touch('key-file');

        $this->setConfig([
            'algorithm' => 'OPENSSL',
            'key' => 'key-file',
        ]);

        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'key-file', $this->config->getPrivateKeyPath());
        self::assertNull($this->config->getPrivateKeyPassphrase());
        self::assertFalse($this->config->promptForPrivateKey());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
            [
                'Using an OpenSSL signature is deprecated and will be removed in 5.0.0. Please check https://github.com/box-project/box/blob/main/doc/phar-signing.md for alternatives.',
            ],
            $this->config->getWarnings(),
        );
    }

    public function test_the_key_pass_can_be_configured(): void
    {
        FS::touch('key-file');

        $this->setConfig([
            'algorithm' => 'OPENSSL',
            'key' => 'key-file',
            'key-pass' => true,
        ]);

        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'key-file', $this->config->getPrivateKeyPath());
        self::assertNull($this->config->getPrivateKeyPassphrase());
        self::assertTrue($this->config->promptForPrivateKey());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
            [
                'Using an OpenSSL signature is deprecated and will be removed in 5.0.0. Please check https://github.com/box-project/box/blob/main/doc/phar-signing.md for alternatives.',
            ],
            $this->config->getWarnings(),
        );

        foreach ([false, null] as $keyPass) {
            $this->setConfig([
                'algorithm' => 'OPENSSL',
                'key' => 'key-file',
                'key-pass' => $keyPass,
            ]);

            self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'key-file', $this->config->getPrivateKeyPath());
            self::assertNull($this->config->getPrivateKeyPassphrase());
            self::assertFalse($this->config->promptForPrivateKey());

            if (null === $keyPass) {
                self::assertSame(
                    ['The "key-pass" setting can be omitted since is set to its default value'],
                    $this->config->getRecommendations(),
                );
            }

            self::assertSame(
                [
                    'Using an OpenSSL signature is deprecated and will be removed in 5.0.0. Please check https://github.com/box-project/box/blob/main/doc/phar-signing.md for alternatives.',
                ],
                $this->config->getWarnings(),
            );
        }

        $this->setConfig([
            'algorithm' => 'OPENSSL',
            'key' => 'key-file',
            'key-pass' => 'weak-password',
        ]);

        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'key-file', $this->config->getPrivateKeyPath());
        self::assertSame('weak-password', $this->config->getPrivateKeyPassphrase());
        self::assertFalse($this->config->promptForPrivateKey());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
            [
                'Using an OpenSSL signature is deprecated and will be removed in 5.0.0. Please check https://github.com/box-project/box/blob/main/doc/phar-signing.md for alternatives.',
            ],
            $this->config->getWarnings(),
        );
    }

    public static function passFileFreeSigningAlgorithmProvider(): iterable
    {
        yield ['MD5', SigningAlgorithm::MD5];
        yield ['SHA1', SigningAlgorithm::SHA1];
        yield ['SHA256', SigningAlgorithm::SHA256];
        yield ['SHA512', SigningAlgorithm::SHA512];
    }
}
