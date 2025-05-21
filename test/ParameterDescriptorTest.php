<?php

declare(strict_types=1);

namespace Asika\SimpleConsole\Test;

use Asika\SimpleConsole\ArgvParser;
use Asika\SimpleConsole\ParameterDescriptor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParameterDescriptorTest extends TestCase
{
    #[DataProvider('lineProvider')]
    public function testLine(\Closure $configure, string $expected, ?string $message = null)
    {
        $parser = new ArgvParser();
        $configure($parser);
        $descriptor = new ParameterDescriptor($parser);

        $parameter = iterator_to_array($parser->parameters)[0];

        $line = $descriptor::line($parameter);

        self::assertEquals(
            $expected,
            $line,
            $message
        );
    }

    public static function lineProvider(): array
    {
        return [
            [
                ''
            ]
        ];
    }
}
