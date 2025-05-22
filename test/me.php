<?php

use Asika\SimpleConsole\Console;

include_once __DIR__ . '/../src/Console.php';

$app = new Console();
// Arguments
$app->addParameter('name', type: $app::STRING, description: 'Your name', required: true);
$app->addParameter('age', type: $app::INT, description: 'Your age')
    ->default(20);

// Name starts with `-` or `--` will be treated as option
$app->addParameter('--height', type: $app::FLOAT, description: 'Your height', required: true);
$app->addParameter('--location|-l', type: $app::STRING, description: 'Live location', required: true);
$app->addParameter('--muted|-m', type: $app::BOOLEAN, description: 'Is muted');

$app->helpHeader = <<<HEADER
[Console] SHOW ME - v1.0

This command can show personal information.
HEADER;

$app->commandName = 'show-me.php';
$app->help = <<<HELP
$ show-me.php John 18 
$ show-me.php John 18 --location=Europe --height 1.75 

...more please see https://show-me.example
HELP;


$app->execute(
    argv: $argv,
    main: function () use ($app) {
        $app->writeln('Hello');
        $app->writeln('Name: ' . $this->get('name'));
        $app->writeln('Age: ' . $this->get('age'));
        $app->writeln('Height: ' . $this->get('height'));
        $app->writeln('Location: ' . $this->get('location'));
        $app->writeln('Muted: ' . ($this->get('muted') ? 'Y' : 'N'));

        return $app::SUCCESS;
    }
);


