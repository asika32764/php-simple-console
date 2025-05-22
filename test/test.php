<?php

use Asika\SimpleConsole\Console;

include_once __DIR__ . '/../src/Console.php';

$app = new class () extends Console
{

    protected function configure(): void
    {
        // Arguments
        $this->addParameter('name', type: $this::STRING, description: 'Your name', required: true);
        $this->addParameter('age', type: $this::LEVEL, description: 'Your age');

        // Name starts with `-` or `--` will be treated as option
        $this->addParameter('--height', type: $this::FLOAT, description: 'Your height', required: true);
        $this->addParameter('--location|-l', type: $this::STRING, description: 'Live location', required: true);
        $this->addParameter('--muted|-m', type: $this::BOOLEAN, description: 'Is muted');
    }

    protected function doExecute(): int|bool
    {
        $this->writeln('Hello');
        $this->writeln('Name: ' . $this->get('name'));
        $this->writeln('Age: ' . $this->get('age'));
        $this->writeln('Height: ' . $this->get('height'));
        $this->writeln('Location: ' . $this->get('location'));
        $this->writeln('Muted: ' . ($this->get('muted') ? 'Y' : 'N'));

        return $this::SUCCESS;
    }
};
$app->execute();

