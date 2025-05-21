<?php

use Asika\SimpleConsole\ParameterType;

include_once __DIR__ . '/../vendor/autoload.php';

$command = new \Symfony\Component\Console\Command\Command();
$command->addOption(
    'foo',
    'f',
    \Symfony\Component\Console\Input\InputOption::VALUE_NEGATABLE
);
