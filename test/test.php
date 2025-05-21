<?php

use Asika\SimpleConsole\ParameterType;

include_once __DIR__ . '/../vendor/autoload.php';

$app = new \Asika\SimpleConsole\SimpleConsole();
$app->execute(
    main: function () use ($app) {
        $app->mustExec(
            'ls sddfsg',
        );
    }
);
