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

use function current;
use Generator;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\Php;
use stdClass;

/**
 * @covers \KevinGH\Box\Configuration\Configuration
 * @group config
 */
class ConfigurationPhpCompactorTest extends ConfigurationTestCase
{
    public function test_the_PHP_compactor_can_be_registered(): void
    {
        $this->setConfig([
            'compactors' => [
                Php::class,
            ],
        ]);

        $compactors = $this->config->getCompactors();

        $this->assertCount(1, $compactors);

        /** @var Compactor $compactor */
        $compactor = current($compactors->toArray());

        $this->assertInstanceOf(Php::class, $compactor);

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_PHP_compactor_ignored_annotations_can_be_configured(): void
    {
        $this->setConfig([
            'annotations' => (object) [
                'ignore' => [
                    'author',
                    'license',
                ],
            ],
            'compactors' => [
                Php::class,
            ],
        ]);

        $compactors = $this->config->getCompactors();

        $this->assertCount(1, $compactors);

        /** @var Compactor $compactor */
        $compactor = current($compactors->toArray());

        $this->assertInstanceOf(Php::class, $compactor);

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_PHP_compactor_annotations_are_configured_with_their_default_values(): void
    {
        foreach ([true, null] as $annotations) {
            $this->setConfig([
                'annotations' => $annotations,
                'compactors' => [
                    Php::class,
                ],
            ]);

            $compactors = $this->config->getCompactors();

            $this->assertCount(1, $compactors);

            $this->assertSame(
                ['The "annotations" setting can be omitted since is set to its default value'],
                $this->config->getRecommendations()
            );
            $this->assertSame([], $this->config->getWarnings());
        }
    }

    /**
     * @dataProvider provideAnnotationConfigurationsWithoutPhpCompactorRegistered
     *
     * @param mixed $annotationValue
     */
    public function test_a_warning_is_given_if_the_PHP_compactor_annotations_are_configured_but_no_PHP_compactor_is_registered(
        $annotationValue,
        array $expectedRecommendations,
        array $expectedWarnings
    ): void {
        $this->setConfig([
            'annotations' => $annotationValue,
        ]);

        $compactors = $this->config->getCompactors();

        $this->assertCount(0, $compactors);

        $this->assertSame($expectedRecommendations, $this->config->getRecommendations());
        $this->assertSame($expectedWarnings, $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_PHP_compactor_ignored_annotations_are_configured_with_their_default_values(): void
    {
        $this->setConfig([
            'annotations' => (object) [
                'ignore' => [],
            ],
        ]);

        $compactors = $this->config->getCompactors();

        $this->assertCount(0, $compactors);

        $this->assertSame(
            ['The "annotations#ignore" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations()
        );
        $this->assertSame(
            ['The "annotations" setting has been set but is ignored since no PHP compactor has been configured'],
            $this->config->getWarnings()
        );
    }

    /**
     * @dataProvider providePhpContentsToCompact
     */
    public function test_ignored_annotations_are_provided_to_the_PHP_compactor(
        array $config,
        string $contents,
        string $expected
    ): void {
        $this->setConfig($config);

        $compactors = $this->config->getCompactors();

        $this->assertCount(1, $compactors);

        /** @var Compactor $compactor */
        $compactor = current($compactors->toArray());

        $this->assertInstanceOf(Php::class, $compactor);

        $actual = $compactor->compact('path/to/file.php', $contents);

        $this->assertSame($expected, $actual);
    }

    public function provideAnnotationConfigurationsWithoutPhpCompactorRegistered(): Generator
    {
        $defaultWarning = 'The "annotations" setting has been set but is ignored since no PHP compactor has been configured';

        yield [
            (object) [
                'ignore' => [
                    'author',
                    'license',
                ],
            ],
            [],
            [$defaultWarning],
        ];

        yield [
            true,
            ['The "annotations" setting can be omitted since is set to its default value'],
            [$defaultWarning],
        ];

        yield [
            false,
            [],
            [$defaultWarning],
        ];

        yield [
            new stdClass(),
            [],
            [
                $defaultWarning,
                'The "annotations" setting has been set but no "ignore" setting has been found, hence "annotations" is treated as if it is set to `false`',
            ],
        ];
    }

    public function providePhpContentsToCompact(): Generator
    {
        yield [
            [
                'annotations' => (object) [
                    'ignore' => [
                        'author',
                        ' license ',
                        '',
                    ],
                ],
                'compactors' => [Php::class],
            ],
            <<<'PHP'
<?php

/**
 * Function comparing the two given values
 *
 * @param int $x
 * @param int $y
 *
 * @return int
 *
 * @author Théo Fidry
 * @LICENSE MIT
 *
 * @Acme(type = "function")
 */
function foo($x, $y): int {
    // Compare the two values
    return $x <=> $y;
}
PHP
            ,
            <<<'PHP'
<?php

/**
@param
@param
@return
@Acme(type="function")







*/
function foo($x, $y): int {

 return $x <=> $y;
}
PHP
        ];

        $falseAnnotationConfigs = [
            false,
            new stdClass(),
            (object) [
                'ignore' => [],
            ],
            (object) [
                'ignore' => null,
            ],
        ];

        foreach ($falseAnnotationConfigs as $config) {
            yield [
                [
                    'annotations' => $config,
                    'compactors' => [Php::class],
                ],
                <<<'PHP'
<?php

/**
 * Function comparing the two given values
 *
 * @param int $x
 * @param int $y
 *
 * @return int
 *
 * @author Théo Fidry
 * @LICENSE MIT
 *
 * @Acme(type = "function")
 */
function foo($x, $y): int {
    // Compare the two values
    return $x <=> $y;
}
PHP
                ,
                <<<'PHP'
<?php

/**
@param
@param
@return
@author
@LICENSE
@Acme(type="function")





*/
function foo($x, $y): int {

 return $x <=> $y;
}
PHP
            ];
        }

        $defaultAnnotationConfigs = [
            null,
            true,
        ];

        foreach ($defaultAnnotationConfigs as $config) {
            yield [
                [
                    'annotations' => $config,
                    'compactors' => [Php::class],
                ],
                <<<'PHP'
<?php

/**
 * Function comparing the two given values
 *
 * @param int $x
 * @param int $y
 *
 * @return int
 *
 * @author Théo Fidry
 * @LICENSE MIT
 *
 * @Acme(type = "function")
 */
function foo($x, $y): int {
    // Compare the two values
    return $x <=> $y;
}
PHP
                ,
                <<<'PHP'
<?php

/**
@Acme(type="function")










*/
function foo($x, $y): int {

 return $x <=> $y;
}
PHP
            ];
        }

        yield [
            [
                'compactors' => [Php::class],
            ],
            <<<'PHP'
<?php

/**
 * Function comparing the two given values
 *
 * @param int $x
 * @param int $y
 *
 * @return int
 *p
 * @author Théo Fidry
 * @LICENSE MIT
 *
 * @Acme(type = "function")
 */
function foo($x, $y): int {
    // Compare the two values
    return $x <=> $y;
}
PHP
            ,
            <<<'PHP'
<?php

/**
@Acme(type="function")










*/
function foo($x, $y): int {

 return $x <=> $y;
}
PHP
        ];
    }
}
