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

namespace KevinGH\Box\AutoReview;

use Fidry\FileSystem\FS;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Webmozart\Assert\Assert;
use function array_diff;
use function array_filter;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function json_decode;
use function preg_match;
use function preg_match_all;
use function sort;
use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
#[CoversNothing]
class DocumentationSchemaTest extends TestCase
{
    private const CONFIGURATION_DOC_PATH = __DIR__.'/../../doc/configuration.md';
    private const SCHEMA_PATH = __DIR__.'/../../res/schema.json';

    public function test_the_schema_keys_are_ordered_lexicographically(): void
    {
        $schemaKeys = $this->retrieveSchemaKeys();

        $expectedKeys = array_unique($schemaKeys);
        sort($expectedKeys);

        self::assertSame($expectedKeys, $schemaKeys);
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

        self::assertSame($schemaKeys, ['$schema', ...$docKeys]);
    }

    public function test_all_the_doc_keys_are_valid(): void
    {
        $docKeys = $this->retrieveDocKeys();

        self::assertSame(
            array_unique($docKeys),
            $docKeys,
            'Did not expect to find duplicated keys in the documentation',
        );

        $schemaKeys = $this->retrieveSchemaKeys();

        self::assertSame(
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

        self::assertEquals($schemaKeys, ['$schema', ...$docKeys]);
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
                FS::getFileContents(self::CONFIGURATION_DOC_PATH),
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
            FS::getFileContents(self::SCHEMA_PATH),
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
            FS::getFileContents(self::CONFIGURATION_DOC_PATH),
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
