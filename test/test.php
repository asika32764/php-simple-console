<?php

include_once __DIR__ . '/../vendor/autoload.php';

class Foo extends \Asika\SimpleConsole\Console
{
    protected $help = <<<HELP
[Usage] test.php

[Options]
    h | help    Show help info.
HELP;
}

$app = new Foo;
$app->execute(function ($app) {
    $this->out('Hello');

    $a = $this->in('Are you sure [Y/n]', true, true);

    $this->out($a);
});
