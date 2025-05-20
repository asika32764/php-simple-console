<?php

use Asika\SimpleConsole\ArgumentType;

include_once __DIR__ . '/../src/SimpleConsole.php';

$parser = new \Asika\SimpleConsole\ArgumentParser();
$parser->addParameter('name', ArgumentType::STRING, 'Your name');
$parser->addParameter(['--foo', '-f'], ArgumentType::STRING, 'Foo option');
$args = $parser->parse($argv);

print_r($args);
