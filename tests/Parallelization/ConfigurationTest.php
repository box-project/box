<?php

namespace KevinGH\Box\Parallelization;

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Parallelization\Configuration
 */
final class ConfigurationTest extends TestCase
{
    /**
     * @dataProvider configurationProvider
     */
    public function test_it_can_be_serialized_and_deserialized(Configuration $configuration): void
    {
        $unserializedBatchResult = BatchResult::unserialize($configuration->serialize());

        self::assertEquals($configuration, $unserializedBatchResult);
    }

    public static function configurationProvider(): iterable
    {
        yield 'empty' => [
            new BatchResult(
                [],
                new SymbolsRegistry(),
            ),
        ];

        $symbolsRegistry = new SymbolsRegistry();
        $symbolsRegistry->recordClass(
            new FullyQualified('Box\Func'),
            new FullyQualified('Scoped\Box\Func'),
        );

        yield 'nominal' => [
            new BatchResult(
                [
                    '/path/to/file.php',
                    '<?php echo "Hello world!";'
                ],
                $symbolsRegistry,
            ),
        ];
    }
}
