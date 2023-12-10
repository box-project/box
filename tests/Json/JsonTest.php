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

namespace KevinGH\Box\Json;

use Fidry\FileSystem\FS;
use KevinGH\Box\Test\FileSystemTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Seld\JsonLint\ParsingException;
use stdClass;
use Throwable;
use Webmozart\Assert\Assert;
use function is_object;
use function json_decode;
use function mb_convert_encoding;

/**
 * @requires extension mbstring
 * @internal
 */
#[CoversClass(Json::class)]
class JsonTest extends FileSystemTestCase
{
    private Json $json;

    protected function setUp(): void
    {
        parent::setUp();

        $this->json = new Json();
    }

    #[DataProvider('jsonToLintProvider')]
    public function test_it_can_lint_a_json_string(string $json, ?Throwable $expectedThrowable): void
    {
        try {
            $this->json->lint($json);

            if (null !== $expectedThrowable) {
                self::fail('Expected throwable to be thrown.');
            }
        } catch (Throwable $throwable) {
            if (null === $expectedThrowable) {
                self::fail('Did not except throwable to be thrown.');
            }

            self::assertSame($expectedThrowable::class, $throwable::class);
            self::assertSame($expectedThrowable->getMessage(), $throwable->getMessage());

            return;
        }

        self::assertTrue(true);
    }

    #[DataProvider('jsonToDecodeProvider')]
    public function test_it_can_decode_a_json_string(string $json, bool $assoc, mixed $expected, ?Throwable $expectedThrowable): void
    {
        if (null === $expected) {
            Assert::notNull($expectedThrowable);
        } else {
            Assert::null($expectedThrowable);
        }

        try {
            $actual = $this->json->decode($json, $assoc);

            if (null !== $expectedThrowable) {
                self::fail('Expected throwable to be thrown.');
            }
        } catch (AssertionFailedError $error) {
            throw $error;
        } catch (Throwable $throwable) {
            if (null === $expectedThrowable) {
                self::fail('Did not except throwable to be thrown: '.$throwable->getMessage());
            }

            self::assertSame($expectedThrowable::class, $throwable::class);
            self::assertSame($expectedThrowable->getMessage(), $throwable->getMessage());

            return;
        }

        if (is_object($expected)) {
            self::assertEquals($expected, $actual);
        } else {
            self::assertSame($expected, $actual);
        }
    }

    public function test_it_can_decode_a_file(): void
    {
        FS::dumpFile('data.json', '{}');

        $decoded = $this->json->decodeFile('data.json');

        self::assertEquals(new stdClass(), $decoded);

        $decoded = $this->json->decodeFile('data.json', true);

        self::assertSame([], $decoded);

        FS::dumpFile('data.json');

        try {
            $this->json->decodeFile('data.json', true);

            self::fail('Expected exception to be thrown.');
        } catch (ParsingException $exception) {
            self::assertStringStartsWith(
                'Parse error on line 1:',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_can_validate_a_file_against_a_schema(): void
    {
        $schema = json_decode(
            <<<'JSON'
                {
                    "description": "Schema description",
                    "properties": {
                        "foo": {
                            "description": "The foo property",
                            "type": ["string"]
                        },
                        "bar": {
                            "description": "The foo property",
                            "type": ["string"]
                        }
                    }
                }

                JSON,
            false,
        );

        FS::touch('data.json');

        $this->json->validate(
            'data.json',
            (static function (): stdClass {
                $data = new stdClass();
                $data->foo = 'bar';

                return $data;
            })(),
            $schema,
        );

        try {
            $this->json->validate(
                'data.json',
                (static function (): stdClass {
                    $data = new stdClass();
                    $data->foo = false;
                    $data->bar = true;

                    return $data;
                })(),
                $schema,
            );

            self::fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            self::assertSame(
                <<<'EOF'
                    "data.json" does not match the expected JSON schema:
                      - foo : Boolean value found, but a string is required
                      - bar : Boolean value found, but a string is required
                    EOF,
                $exception->getMessage(),
            );
            self::assertSame(
                [
                    'foo : Boolean value found, but a string is required',
                    'bar : Boolean value found, but a string is required',
                ],
                $exception->getErrors(),
            );
            self::assertSame('data.json', $exception->getValidatedFile());
            self::assertSame(0, $exception->getCode());
            self::assertNull($exception->getPrevious());
        }
    }

    public static function jsonToLintProvider(): iterable
    {
        yield ['{}', null];

        yield [
            '',
            new ParsingException(
                <<<'EOF'
                    Parse error on line 1:

                    ^
                    Expected one of: 'STRING', 'NUMBER', 'NULL', 'TRUE', 'FALSE', '{', '['
                    EOF,
            ),
        ];
    }

    public static function jsonToDecodeProvider(): iterable
    {
        yield ['{}', true, [], null];
        yield ['{}', false, new stdClass(), null];

        yield [
            <<<'JSON'
                {
                    "foo": {
                        "bar": [],
                        "baz": ["a", "b"],
                        "far": {}
                    }
                }
                JSON,
            true,
            [
                'foo' => [
                    'bar' => [],
                    'baz' => ['a', 'b'],
                    'far' => [],
                ],
            ],
            null,
        ];

        yield [
            <<<'JSON'
                {
                    "foo": {
                        "bar": [],
                        "baz": ["a", "b"],
                        "far": {}
                    }
                }
                JSON,
            false,
            (static function () {
                $data = new stdClass();
                $data->foo = new stdClass();
                $data->foo->bar = [];
                $data->foo->baz = ['a', 'b'];
                $data->foo->far = new stdClass();

                return $data;
            })(),
            null,
        ];

        yield [
            '',
            true,
            null,
            new ParsingException(
                <<<'EOF'
                    Parse error on line 1:

                    ^
                    Expected one of: 'STRING', 'NUMBER', 'NULL', 'TRUE', 'FALSE', '{', '['
                    EOF,
            ),
        ];

        yield [
            mb_convert_encoding('ü', 'latin1', 'auto'),
            true,
            null,
            new ParsingException('JSON decoding failed: Malformed UTF-8 characters, possibly incorrectly encoded'),
        ];

        yield [
            "\xEF\xBB\xBF".'{"foo": "bar"}',
            true,
            null,
            new ParsingException('BOM detected, make sure your input does not include a Unicode Byte-Order-Mark'),
        ];
    }
}
