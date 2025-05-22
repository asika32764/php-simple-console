<?php

use Asika\SimpleConsole\Console;

include_once __DIR__ . '/../vendor/autoload.php';

$app = new class () extends Console
{

    protected function configure(): void
    {
        $this->addParameter('task', type: $this::STRING, description: 'Task (configure|build|make|move|clear)', required: true);
        $this->addParameter('--lib-path', type: $this::STRING);
        $this->addParameter('--temp-path', type: $this::STRING);
        $this->addParameter('--nested', type: $this::STRING);
        $this->addParameter('--all', type: $this::STRING);
    }

    protected function doExecute(): int|bool
    {
        $task = $this['task'];

        $params = [];

        foreach ($this->params as $k => $v) {
            $params[\Windwalker\Utilities\StrNormalize::toCamelCase($k)] = $v;
        }

        return $this->$task(...$params);
    }

    // ...$args is required, otherwise the redundant params will make method calling error
    protected function build(string $libPath, string $tempPath, ...$args): int
    {
        $this->writeln("Building: $libPath | $tempPath");
        return 0;
    }

    protected function clear(string $nested, string $dir, ...$args): int
    {
        $this->writeln("Clearing: $nested | $dir");
        return 0;
    }
};
$app->execute();

