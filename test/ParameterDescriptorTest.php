<?php

declare(strict_types=1);

namespace Asika\SimpleConsole\Test;

use Asika\SimpleConsole\ParameterType;
use Asika\SimpleConsole\ArgvParser;
use Asika\SimpleConsole\Parameter;
use Asika\SimpleConsole\ParameterDescriptor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParameterDescriptorTest extends TestCase
{
    #[DataProvider('lineProvider')]
    public function testLine(\Closure $configure, string $expected, string $message = '')
    {
        $parser = new ArgvParser();
        $configure($parser);
        $descriptor = new ParameterDescriptor($parser);

        /** @var Parameter $parameter */
        $parameter = array_values(iterator_to_array($parser->parameters))[0];
        $maxWidth = 0;

        if ($parameter->isArg) {
            $line = $descriptor::describeArgument($parameter, $maxWidth);
        } else {
            $line = $descriptor::describeOption($parameter, $maxWidth);
        }

        $line = implode('  ', $line);

        self::assertEquals(
            $expected,
            $line,
            $message
        );
    }

    public static function lineProvider(): array
    {
        return [
            'Simple arg' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('name', ParameterType::STRING, 'My Name');
                },
                'name  My Name',
            ],
            'Arg with default' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('name', ParameterType::STRING, 'My Name', default: 'John Doe');
                },
                'name  My Name [default: "John Doe"]',
            ],
            'Arg without desc' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('name', ParameterType::STRING, '', default: 'John Doe');
                },
                'name   [default: "John Doe"]',
            ],

            // Options
            'Simple bool option' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--muted', ParameterType::BOOLEAN, 'Muted description');
                },
                '--muted  Muted description',
            ],
            'Option bool with shortcut' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--muted|-m', ParameterType::BOOLEAN, 'Muted description');
                },
                '-m, --muted  Muted description',
            ],
            'Option bool with multiple shortcuts' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--muted|-m|-M|--quite', ParameterType::BOOLEAN, 'Muted description');
                },
                '-m|-M, --muted|--quite  Muted description',
            ],
            'Option bool negatable' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--muted', ParameterType::BOOLEAN, 'Muted description', negatable: true);
                },
                '--muted|--no-muted  Muted description',
            ],
            'Option bool negatable with default' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--muted', ParameterType::BOOLEAN, 'Muted description', default: true, negatable: true);
                },
                '--muted|--no-muted  Muted description [default: true]',
            ],
            'Option string' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--foo', ParameterType::STRING, 'Foo description');
                },
                '--foo[=FOO]  Foo description',
            ],
            'Option string with default' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--foo|-f', ParameterType::STRING, 'Foo description', default: 'Flower');
                },
                '-f, --foo[=FOO]  Foo description [default: "Flower"]',
            ],
            'Option string with required' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--foo|-f', ParameterType::STRING, 'Foo description', required: true);
                },
                '-f, --foo=FOO  Foo description',
            ],
            'Option int' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--price|-p', ParameterType::INT, 'Price description', default: 123);
                },
                '-p, --price[=PRICE]  Price description [default: 123]',
            ],
            'Option array' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--tag|-t', ParameterType::ARRAY, 'Tag description', default: ['a', 'b']);
                },
                '-t, --tag[=TAG]  Tag description [default: ["a","b"]] (multiple values allowed)',
            ],
        ];
    }

    #[DataProvider('synopsisProvider')]
    public function testSynopsis(\Closure $configure, string $expected, string $message = ''): void
    {
        $parser = new ArgvParser();
        $configure($parser);
        $descriptor = new ParameterDescriptor($parser);

        $line = $descriptor::synopsis($parser);

        self::assertEquals(
            $expected,
            $line,
            $message
        );
    }

    public static function synopsisProvider(): array
    {
        return [
            'Single argument' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('name', ParameterType::STRING, 'Name description', default: 'John Doe');
                },
                '[<name>]'
            ],
            'Single argument with required' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('name', ParameterType::STRING, 'Name description', required: true);
                },
                '<name>'
            ],
            'Only options' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--foo|-f', ParameterType::STRING, 'Foo description', required: true);
                    $parser->addParameter('--price|-p', ParameterType::NUMERIC, 'Price description');
                },
                '[-f|--foo FOO] [-p|--price [PRICE]]'
            ],
            'Options with negative' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('--quiet|-q', ParameterType::STRING, 'Quiet description', default: true, negatable: true);
                    $parser->addParameter('--muted|-m', ParameterType::BOOLEAN, 'Muted description', negatable: true);
                },
                '[-q|--quiet|--no-quiet] [-m|--muted|--no-muted]'
            ],
            'Args and options' => [
                function (ArgvParser $parser) {
                    $parser->addParameter('name', ParameterType::STRING, 'Name description', required: true);
                    $parser->addParameter('age', ParameterType::INT, 'Age description', default: 18);
                    $parser->addParameter('steps', ParameterType::INT, 'Step description');
                    $parser->addParameter('--gender|-g', ParameterType::STRING, 'Gender description');
                    $parser->addParameter('--loc|-l', ParameterType::STRING, 'Location description');
                },
                '[-g|--gender [GENDER]] [-l|--loc [LOC]] [--] <name> [<age> [<steps>]]'
            ],
        ];
    }
}
