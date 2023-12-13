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

namespace KevinGH\Box\Compactor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
#[CoversClass(Placeholder::class)]
class PlaceholderTest extends CompactorTestCase
{
    #[DataProvider('filesContentsProvider')]
    public function test_it_replaces_the_placeholders(array $placeholders, string $contents, string $expected): void
    {
        $actual = (new Placeholder($placeholders))->compact('random file', $contents);

        self::assertSame($expected, $actual);
    }

    public static function compactorProvider(): iterable
    {
        yield 'empty' => [
            new Placeholder([]),
        ];

        yield 'nominal' => [
            new Placeholder(['@foo@' => 'bar']),
        ];
    }

    public static function filesContentsProvider(): iterable
    {
        yield [[], '', ''];

        yield [['@foo@' => 'bar'], '', ''];

        yield [['@foo@' => 'bar'], 'foo', 'foo'];

        yield [['@foo@' => 'bar'], '@foo@', 'bar'];

        yield [
            [
                '@foo@' => 'oof',
                '@bar@' => '@rab@',
                '@baz@' => 'zab',
            ],
            <<<'EOF'
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam at justo nec sem pretium blandit ut eu nulla. Phasellus
                @foo@
                sed varius ipsum, quis convallis ipsum. Orci varius natoque penatibus et magnis dis parturient montes, nascetur
                @foo
                ridiculus mus. Nunc vel tortor posuere, dictum diam aliquam, vestibulum augue. Integer neque arcu, finibus eget leo
                foo@
                vitae, cursus pharetra eros. Nulla scelerisque felis a quam blandit, ac convallis arcu feugiat. Maecenas sem quam,
                @foo@bar@
                gravida quis dictum et, elementum a augue. In interdum, orci eu pulvinar tristique, quam erat laoreet risus, nec viverra
                @foo@@bar@
                @foo@foo@
                purus augue a leo. Nulla auctor, augue ac ultricies imperdiet, erat purus interdum libero, eu condimentum tellus nulla
                vel nisi.
                EOF,
            <<<'EOF'
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam at justo nec sem pretium blandit ut eu nulla. Phasellus
                oof
                sed varius ipsum, quis convallis ipsum. Orci varius natoque penatibus et magnis dis parturient montes, nascetur
                @foo
                ridiculus mus. Nunc vel tortor posuere, dictum diam aliquam, vestibulum augue. Integer neque arcu, finibus eget leo
                foo@
                vitae, cursus pharetra eros. Nulla scelerisque felis a quam blandit, ac convallis arcu feugiat. Maecenas sem quam,
                oofbar@
                gravida quis dictum et, elementum a augue. In interdum, orci eu pulvinar tristique, quam erat laoreet risus, nec viverra
                oof@rab@
                ooffoo@
                purus augue a leo. Nulla auctor, augue ac ultricies imperdiet, erat purus interdum libero, eu condimentum tellus nulla
                vel nisi.
                EOF,
        ];

        yield [
            [
                '_@_foo_@_' => 'oof',
                '_@_bar_@_' => '@rab@',
                '_@_baz_@_' => 'zab',
            ],
            <<<'EOF'
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam at justo nec sem pretium blandit ut eu nulla. Phasellus
                _@_foo_@_
                sed varius ipsum, quis convallis ipsum. Orci varius natoque penatibus et magnis dis parturient montes, nascetur
                _@_foo
                ridiculus mus. Nunc vel tortor posuere, dictum diam aliquam, vestibulum augue. Integer neque arcu, finibus eget leo
                foo_@_
                vitae, cursus pharetra eros. Nulla scelerisque felis a quam blandit, ac convallis arcu feugiat. Maecenas sem quam,
                _@_foo_@_bar_@_
                gravida quis dictum et, elementum a augue. In interdum, orci eu pulvinar tristique, quam erat laoreet risus, nec viverra
                _@_foo_@__@_bar_@_
                _@_foo_@_foo_@_
                purus augue a leo. Nulla auctor, augue ac ultricies imperdiet, erat purus interdum libero, eu condimentum tellus nulla
                vel nisi.
                EOF,
            <<<'EOF'
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam at justo nec sem pretium blandit ut eu nulla. Phasellus
                oof
                sed varius ipsum, quis convallis ipsum. Orci varius natoque penatibus et magnis dis parturient montes, nascetur
                _@_foo
                ridiculus mus. Nunc vel tortor posuere, dictum diam aliquam, vestibulum augue. Integer neque arcu, finibus eget leo
                foo_@_
                vitae, cursus pharetra eros. Nulla scelerisque felis a quam blandit, ac convallis arcu feugiat. Maecenas sem quam,
                oofbar_@_
                gravida quis dictum et, elementum a augue. In interdum, orci eu pulvinar tristique, quam erat laoreet risus, nec viverra
                oof@rab@
                ooffoo_@_
                purus augue a leo. Nulla auctor, augue ac ultricies imperdiet, erat purus interdum libero, eu condimentum tellus nulla
                vel nisi.
                EOF,
        ];
    }
}
