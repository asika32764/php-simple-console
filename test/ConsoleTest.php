<?php

declare(strict_types=1);

namespace Asika\SimpleConsole\Test;

use Asika\SimpleConsole\SimpleConsole;
use PHPUnit\Framework\TestCase;

class ConsoleTest extends TestCase
{
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
                'steps' => false,
                'bar' => 'BAR',
            ],
            $parsed
        );
    }

    public function testHelp()
    {
        $app = new SimpleConsole();
        $app->addParameter('name', $app::STRING, 'Name Description', required: true);
        $app->addParameter('steps', $app::NUMERIC, 'Steps Description', default: 20);
        $app->addParameter('--foo|-f', $app::STRING, 'Foo Description', required: true);
        $app->addParameter('--bar|-b|-c', $app::STRING, 'Bar Description', default: 'BAR');
        $app->addParameter('--muted|-m', $app::STRING, 'Muted Description', negatable: true);

        $argv = [
            'command',
            'Hello',
            '--foo',
            'QWQ',
        ];

        $app->execute(
            $argv,
            function () use ($app) {
                $app->showHelp();
            }
        );
    }
}
