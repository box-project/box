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

namespace KevinGH\Box;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use function KevinGH\Box\FileSystem\file_contents;
use PHPUnit\Framework\TestCase;
use function preg_match;
use function preg_match_all;
use function sort;
use Webmozart\Assert\Assert;

/**
 * @coversNothing
 */
class DocumentationSchemaTest extends TestCase
{
    public function test_the_schema_keys_are_ordered_lexicographically(): void
    {
        $schemaKeys = $this->retrieveSchemaKeys();

        $expectedKeys = array_unique($schemaKeys);
        sort($expectedKeys);

        $this->assertSame($expectedKeys, $schemaKeys);
    }

    public function test_the_documentation_schema_is_up_to_date(): void
    {
        $docKeys = $this->retrieveDocSchemaKeys();

        $schemaKeys = array_values(
            array_filter(
                $this->retrieveSchemaKeys(),
                static fn (string $key): bool => 'datetime_format' !== $key,
            ),
        );

        $this->assertSame($schemaKeys, ['$schema', ...$docKeys]);
    }

    public function test_all_the_doc_keys_are_valid(): void
    {
        $docKeys = $this->retrieveDocKeys();

        $this->assertSame(
            array_unique($docKeys),
            $docKeys,
            'Did not expect to find duplicated keys in the documentation',
        );

        $schemaKeys = $this->retrieveSchemaKeys();

        $this->assertSame(
            [],
            array_diff($docKeys, $schemaKeys),
            'Did not expect to find a key in the documentation which is not found in the schema',
        );
    }

    public function test_there_is_a_doc_entry_for_each_schema_key(): void
    {
        $docKeys = $this->retrieveDocKeys();

        sort($docKeys);

        $schemaKeys = array_values(
            array_filter(
                $this->retrieveSchemaKeys(),
                static fn (string $key): bool => 'datetime_format' !== $key,
            ),
        );

        $this->assertEquals($schemaKeys, ['$schema', ...$docKeys]);
    }

    /**
     * @return string[]
     */
    private function retrieveDocSchemaKeys(): array
    {
        Assert::same(
            1,
            preg_match(
                '/```json(?<schema>.*?)```/s',
                file_contents(__DIR__.'/../doc/configuration.md'),
                $matches,
            ),
        );

        return array_keys(json_decode((string) $matches['schema'], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return string[]
     */
    private function retrieveSchemaKeys(): array
    {
        $schema = json_decode(
            file_contents(__DIR__.'/../res/schema.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return array_keys($schema['properties']);
    }

    /**
     * @return string[]
     */
    private function retrieveDocKeys(): array
    {
        preg_match_all(
            '/#+ [\p{L}\\-\s]+\(`(.*?)`(?:[\p{L}\\-\s]+`(.*?)`)?\)/u',
            file_contents(__DIR__.'/../doc/configuration.md'),
            $matches,
        );

        return array_filter(
            array_merge(
                $matches[1],
                $matches[2],
            ),
        );
    }
}
