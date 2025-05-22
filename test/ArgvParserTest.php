<?php

declare(strict_types=1);

namespace Asika\SimpleConsole\Test;

use Asika\SimpleConsole\ParameterType;
use Asika\SimpleConsole\ArgvParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Windwalker\Test\Traits\BaseAssertionTrait;

use function PHPUnit\Framework\assertEquals;

class ArgvParserTest extends TestCase
{
    use BaseAssertionTrait;

    #[DataProvider('parametersProvider')]
    public function testParameters(array|string $cmd, array|string $expected): void
    {
        if (is_string($expected)) {
            $this->expectExceptionMessage($expected);
        }

        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('steps', ParameterType::INT);
        $parser->addParameter('--lat|-l', ParameterType::FLOAT);
        $parser->addParameter('--user|-u', ParameterType::STRING);
        $parser->addParameter('--price|-p', ParameterType::NUMERIC);
        $parser->addParameter('--muted|-m', ParameterType::BOOLEAN);
        $parser->addParameter('--quite|-q', ParameterType::BOOLEAN);

        if (is_string($cmd)) {
            $cmd = explode(' ', $cmd);
        }

        array_unshift($cmd, 'command');

        $args = $parser->parse($cmd);

        if (is_array($expected)) {
            ksort($expected);
            ksort($args);
            self::assertSame($expected, $args);
        }
    }

    public static function parametersProvider()
    {
        return [
            'Default values' => [
                'Hello 200 --user admin --lat 123.456 --muted -p 500.00',
                [
                    'name' => 'Hello',
                    'user' => 'admin',
                    'steps' => 200,
                    'lat' => 123.456,
                    'price' => 500.00,
                    'muted' => true,
                    'quite' => false,
                ],
            ],
            'Option --user empty' => [
                'Hello 200 --user --lat 123.456 --muted -p 500.00',
                [
                    'name' => 'Hello',
                    'user' => null,
                    'steps' => 200,
                    'lat' => 123.456,
                    'price' => 500.00,
                    'muted' => true,
                    'quite' => false,
                ],
            ],
            'Option --user not provided' => [
                'Hello 200 --lat 123.456 --muted -p 500.00',
                [
                    'name' => 'Hello',
                    'user' => false,
                    'steps' => 200,
                    'lat' => 123.456,
                    'price' => 500.00,
                    'muted' => true,
                    'quite' => false,
                ],
            ],
            'Boolean not provided' => [
                'Hello 200 --lat 123.456 --price 500.00',
                [
                    'name' => 'Hello',
                    'user' => false,
                    'steps' => 200,
                    'lat' => 123.456,
                    'price' => 500.00,
                    'muted' => false,
                    'quite' => false,
                ],
            ],
            'Shortcuts' => [
                'Hello 200 -l 123.456 -p 500.00 -m -q',
                [
                    'name' => 'Hello',
                    'user' => false,
                    'steps' => 200,
                    'lat' => 123.456,
                    'price' => 500.00,
                    'muted' => true,
                    'quite' => true,
                ],
            ],
            'Shortcuts Group' => [
                'Hello 200 -uadmin -l 123.456 -p 500.00 -m -q',
                [
                    'name' => 'Hello',
                    'user' => 'admin',
                    'steps' => 200,
                    'lat' => 123.456,
                    'price' => 500.00,
                    'muted' => true,
                    'quite' => true,
                ],
            ],
            'Boolean shortcut group' => [
                'Hello 200 --lat 123.456 -p 500.00 -mq',
                [
                    'name' => 'Hello',
                    'user' => false,
                    'steps' => 200,
                    'lat' => 123.456,
                    'price' => 500.00,
                    'muted' => true,
                    'quite' => true,
                ],
            ],
            'Wrong argument format' => [
                'Hello foo --lat 123.456 -p 500.00 -mq',
                'Invalid value type for "steps". Expected INT.',
            ],
            'Wrong option format' => [
                'Hello 200 --lat string -p 500.00 -mq',
                'Invalid value type for "lat". Expected FLOAT.',
            ],
        ];
    }

    public function testArgumentRequired(): void
    {
        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('steps', ParameterType::INT, required: true);

        $args = $parser->parse(
            ['command', 'Hello', '200']
        );

        assertEquals(200, $args['steps']);

        $this->expectExceptionMessage('Required argument "steps" is missing.');

        $parser->parse(
            ['command', 'Hello']
        );
    }

    public function testOptionRequired(): void
    {
        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('steps', ParameterType::INT);
        $parser->addParameter('--user|-u', ParameterType::STRING, required: true);

        $args = $parser->parse(
            ['command', 'Hello', '200', '--user', 'admin']
        );

        assertEquals('admin', $args['user']);

        $this->expectExceptionMessage('Required value "user" is missing.');

        $parser->parse(
            ['command', 'Hello', '200', '--user']
        );
    }

    public function testOptionRequiredAndNotProvided(): void
    {
        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('steps', ParameterType::INT);
        $parser->addParameter('--user|-u', ParameterType::STRING, required: true);

        $args = $parser->parse(
            ['command', 'Hello', '200']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'steps' => 200,
                'user' => false,
            ],
            $args
        );
    }

    public function testArgumentArray(): void
    {
        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('steps', ParameterType::ARRAY);
        $parser->addParameter('--user|-u', ParameterType::STRING);

        $args = $parser->parse(
            ['command', 'Hello', 'a', '-u', 'admin', 'b', 'c']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'steps' => ['a', 'b', 'c'],
                'user' => 'admin',
            ],
            $args
        );
    }

    public function testOptionArray(): void
    {
        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('--steps', ParameterType::ARRAY);
        $parser->addParameter('--user|-u', ParameterType::STRING);

        $args = $parser->parse(
            ['command', 'Hello', '--steps=a', '-u', 'admin', '--steps=b', '--steps', 'c']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'steps' => ['a', 'b', 'c'],
                'user' => 'admin',
            ],
            $args
        );
    }

    public function testBooleanNegative(): void
    {
        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('--muted', ParameterType::BOOLEAN, default: true, negatable: true);

        $args = $parser->parse(
            ['command', 'Hello']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'muted' => true,
            ],
            $args
        );

        $args = $parser->parse(
            ['command', 'Hello', '--no-muted']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'muted' => false,
            ],
            $args
        );
    }

    public function testEscapedArguments(): void
    {
        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('cmd', ParameterType::ARRAY);
        $parser->addParameter('--muted', ParameterType::BOOLEAN);

        $args = $parser->parse(
            ['command', 'Hello', '--muted', '--', 'php', 'console', 'make:entity', 'FooBar', '--path', 'abc', '--dir', 'efg']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'muted' => true,
                'cmd' => [
                    'php',
                    'console',
                    'make:entity',
                    'FooBar',
                    '--path',
                    'abc',
                    '--dir',
                    'efg',
                ]
            ],
            $args
        );
    }

    public function testVerbosity()
    {
        $parser = new ArgvParser();
        $parser->addParameter('name', ParameterType::STRING);
        $parser->addParameter('-v', ParameterType::LEVEL);

        $args = $parser->parse(
            ['command', 'Hello']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'v' => 0,
            ],
            $args
        );

        $args = $parser->parse(
            ['command', 'Hello', '-v']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'v' => 1,
            ],
            $args
        );

        $args = $parser->parse(
            ['command', 'Hello', '-vvv']
        );

        self::assertSame(
            [
                'name' => 'Hello',
                'v' => 3,
            ],
            $args
        );
    }
}
