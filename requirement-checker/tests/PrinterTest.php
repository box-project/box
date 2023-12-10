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

namespace KevinGH\RequirementChecker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function ob_get_clean;
use function ob_start;

/**
 * @internal
 */
#[CoversClass(Printer::class)]
class PrinterTest extends TestCase
{
    #[DataProvider('provideTitles')]
    public function test_it_can_print_a_title(
        int $verbosity,
        bool $colors,
        int $width,
        string $message,
        int $messageVerbosity,
        string $expected
    ): void {
        $printer = new Printer($verbosity, $colors, $width);

        ob_start();
        $printer->title($message, $messageVerbosity);
        $actual = ob_get_clean();

        self::assertSame($expected, $actual);
    }

    #[DataProvider('provideErrorRequirements')]
    public function test_it_can_provide_an_error_requirement_message(
        Requirement $requirement,
        int $verbosity,
        bool $colors,
        int $width,
        ?string $expected
    ): void {
        $actual = (new Printer($verbosity, $colors, $width))->getRequirementErrorMessage($requirement);

        self::assertSame($expected, $actual);
    }

    #[DataProvider('provideBlocks')]
    public function test_it_can_print_a_block(
        int $verbosity,
        bool $colors,
        int $width,
        string $title,
        string $message,
        int $messageVerbosity,
        string $expected
    ): void {
        $printer = new Printer($verbosity, $colors, $width);

        ob_start();
        $printer->block($title, $message, $messageVerbosity, 'success');
        $actual = ob_get_clean();

        self::assertSame($expected, $actual);
    }

    public static function provideTitles(): iterable
    {
        yield [
            IO::VERBOSITY_NORMAL,
            false,
            50,
            'This is a title',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'

                This is a title
                ===============


                EOF
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            true,
            50,
            'This is a title',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'
                [33m[0m
                [0m[33mThis is a title[0m
                [0m[33m===============[0m
                [0m[33m[0m
                [0m
                EOF
        ];

        yield [
            IO::VERBOSITY_VERBOSE,
            false,
            50,
            'This is a title',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'

                This is a title
                ===============


                EOF
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            false,
            50,
            'This is a title',
            IO::VERBOSITY_VERBOSE,
            '',
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            false,
            15,
            'This is a very long title',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'

                This is a very
                long title
                ===============


                EOF
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            true,
            15,
            'This is a very long title',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'
                [33m[0m
                [0m[33mThis is a very
                long title[0m
                [0m[33m===============[0m
                [0m[33m[0m
                [0m
                EOF
        ];
    }

    public static function provideErrorRequirements(): iterable
    {
        yield [
            new Requirement(
                new ConditionIsFulfilled(),
                'Test message',
                'Help message'
            ),
            IO::VERBOSITY_NORMAL,
            false,
            50,
            null,
        ];

        yield [
            new Requirement(
                new ConditionIsNotFulfilled(),
                'Test message',
                'Help message'
            ),
            IO::VERBOSITY_NORMAL,
            false,
            50,
            <<<'EOF'
                Help message

                EOF
        ];

        yield [
            new Requirement(
                new ConditionIsNotFulfilled(),
                'Test message',
                'Help message'
            ),
            IO::VERBOSITY_NORMAL,
            true,
            50,
            <<<'EOF'
                Help message

                EOF
        ];
    }

    public static function provideBlocks(): iterable
    {
        yield [
            IO::VERBOSITY_NORMAL,
            false,
            25,
            'OK',
            'This is a block',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'

                                         
                 [OK] This is a          
                      block              
                                         

                EOF
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            true,
            25,
            'OK',
            'This is a block',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'
                [0m
                [0m[30;42m                         [0m
                [0m[30;42m [OK] This is a          [0m
                [0m[30;42m      block              [0m
                [0m[30;42m                         [0m[0m
                [0m
                EOF
        ];

        yield [
            IO::VERBOSITY_VERBOSE,
            false,
            25,
            'OK',
            'This is a block',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'

                                         
                 [OK] This is a          
                      block              
                                         

                EOF
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            false,
            25,
            'OK',
            'This is a block',
            IO::VERBOSITY_VERBOSE,
            '',
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            false,
            20,
            'OK',
            'This is a very long block that should be displayed on 5 lines',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'

                                    
                 [OK] This is       
                      a very long   
                      block that    
                      should be     
                      displayed on  
                      5 lines       
                                    

                EOF
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            true,
            20,
            'OK',
            'This is a very long block that should be displayed on 6 lines',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'
                [0m
                [0m[30;42m                    [0m
                [0m[30;42m [OK] This is       [0m
                [0m[30;42m      a very long   [0m
                [0m[30;42m      block that    [0m
                [0m[30;42m      should be     [0m
                [0m[30;42m      displayed on  [0m
                [0m[30;42m      6 lines       [0m
                [0m[30;42m                    [0m[0m
                [0m
                EOF
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            true,
            20,
            'OK',
            'Your system is ready to run the application.',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'
                [0m
                [0m[30;42m                    [0m
                [0m[30;42m [OK] Your          [0m
                [0m[30;42m      system is     [0m
                [0m[30;42m      ready to run  [0m
                [0m[30;42m      the           [0m
                [0m[30;42m      application.  [0m
                [0m[30;42m                    [0m[0m
                [0m
                EOF
        ];

        yield [
            IO::VERBOSITY_NORMAL,
            true,
            0,
            'OK',
            'Your system is ready to run the application.',
            IO::VERBOSITY_NORMAL,
            <<<'EOF'
                [0m
                [0m[30;42m                                                                                [0m
                [0m[30;42m [OK] Your system is ready to run the application.                              [0m
                [0m[30;42m                                                                                [0m[0m
                [0m
                EOF
        ];
    }
}
