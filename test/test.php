<?php

use Asika\SimpleConsole\ArgumentType;

include_once __DIR__ . '/../vendor/autoload.php';

$parser = new \Asika\SimpleConsole\ArgvParser();
$parser->addParameter('name', ArgumentType::STRING, 'Your name');
$parser->addParameter('tags', ArgumentType::ARRAY, 'tags');
$parser->addParameter('--goo|-g', ArgumentType::ARRAY, 'Goo option', required: true);
$args = $parser->parse($argv);

print_r($args);
