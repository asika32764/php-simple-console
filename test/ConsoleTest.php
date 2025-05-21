<?php

declare(strict_types=1);

namespace Asika\SimpleConsole\Test;

use Asika\SimpleConsole\SimpleConsole;
use PHPUnit\Framework\TestCase;
use Windwalker\Test\Traits\BaseAssertionTrait;

class ConsoleTest extends TestCase
{
    use BaseAssertionTrait;

    public function testExecuteCallback(): void
    {
        $params = [];
        $argv = [
            'command',
            'Hello',
            '--foo',
            'QWQ',
        ];

        $app = new SimpleConsole();
        $app->addParameter('name', $app::STRING);
        $app->addParameter('steps', $app::NUMERIC);
        $app->addParameter('--foo', $app::STRING);
        $app->addParameter('--bar', $app::STRING, default: 'BAR');
        $exitCode = $app->execute(
            argv: $argv,
            main: function () use ($app, &$params) {
                $params = $app->params;
            }
        );

        self::assertEquals(0, $exitCode);
        self::assertSame(
            [
                'name' => 'Hello',
                'foo' => 'QWQ',
                'steps' => false,
                'bar' => 'BAR',
                'help' => false,
                'verbosity' => 0,
            ],
            $params
        );
    }

    public function testExecuteExtends(): void
    {
        $argv = [
            'command',
            'Hello',
            '--foo',
            'QWQ',
        ];

        $app = new class ($parsed) extends SimpleConsole {
            public function __construct(public ?array &$parsed = null)
            {
                parent::__construct();
            }

            protected function configure(): void
            {
                $this->addParameter('name', static::STRING);
                $this->addParameter('steps', static::NUMERIC);
                $this->addParameter('--foo', static::STRING);
                $this->addParameter('--bar', static::STRING, default: 'BAR');
            }

            protected function doExecute(): int
            {
                $this->parsed = $this->params;

                return static::SUCCESS;
            }
        };
        $exitCode = $app->execute($argv);

        self::assertEquals(0, $exitCode);
        self::assertSame(
            [
                'name' => 'Hello',
                'foo' => 'QWQ',
                'help' => false,
                'verbosity' => 0,
                'steps' => false,
                'bar' => 'BAR',
            ],
            $parsed
        );
    }

    public function testHelp(): void
    {
        $fp = fopen('php://memory', 'rb+');

        $app = new SimpleConsole(stdout: $fp);
        $app->addParameter('name', $app::STRING, 'Name Description', required: true);
        $app->addParameter('steps', $app::NUMERIC, 'Steps Description', default: 20);
        $app->addParameter('--foo|-f', $app::STRING, 'Foo Description', required: true);
        $app->addParameter('--bar|-b|-c', $app::STRING, 'Bar Description', default: 'BAR');
        $app->addParameter('--muted|-m', $app::STRING, 'Muted Description', negatable: true);

        $argv = [
            'command',
            'Hello',
            '-h',
        ];
        $app->verbosity = 1;

        $app->execute(
            $argv,
            function () use ($app) {
                //
            }
        );

        rewind($fp);
        $output = stream_get_contents($fp);

        self::assertStringSafeEquals(
            <<<TEXT
            Usage:
              command [options] [--] <name> [<steps>]
            
            Arguments:
              name     Name Description
              steps    Steps Description [default: 20]
            
            Options:
              -f, --foo=FOO             Foo Description
              -b|-c, --bar[=BAR]        Bar Description [default: "BAR"]
              -m, --muted|--no-muted    Muted Description
              -h, --help                Show description of all parameters
              -v, --verbosity           The verbosity level of the output
            TEXT,
            $output
        );
    }
}
